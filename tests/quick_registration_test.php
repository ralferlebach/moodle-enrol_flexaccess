<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace enrol_flexaccess;

use enrol_flexaccess\local\access_controller;
use enrol_flexaccess\local\instance_config;

/**
 * Tests for the quick-registration flow (persistent, loginnable account).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_flexaccess\local\access_controller
 */
final class quick_registration_test extends \advanced_testcase {
    /**
     * Skip when the auth_flexaccess sibling plugin is not installed (per-plugin CI).
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;
        if (!$DB->get_manager()->table_exists('auth_flexaccess_account')) {
            $this->markTestSkipped('Requires the auth_flexaccess sibling plugin to be installed.');
        }
    }

    /**
     * Create a course with an enabled FlexAccess instance allowing quick registration.
     *
     * @return \stdClass The course.
     */
    private function course_allowing_quick(): \stdClass {
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        instance_config::save($enrolid, ['allowquick' => 1]);
        return $course;
    }

    /**
     * Quick registration creates a persistent account that is enrolled and can log in again.
     *
     * @return void
     */
    public function test_quick_registration_creates_loginnable_enrolled_account(): void {
        $this->resetAfterTest();
        $course = $this->course_allowing_quick();

        $result = access_controller::grant_quick_registration((int) $course->id, (object) [
            'email' => 'learner@example.com',
            'firstname' => 'Test',
            'lastname' => 'Learner',
            'password' => 'Str0ng-Pass!23',
        ]);

        $this->assertSame('granted', $result->status);
        $this->assertTrue(is_enrolled(
            \context_course::instance((int) $course->id),
            \core_user::get_user($result->userid)
        ));

        // The account is persistent and can authenticate again (the FlexAccess USP).
        $auth = get_auth_plugin('flexaccess');
        $this->assertTrue($auth->user_login('learner@example.com', 'Str0ng-Pass!23'));
        $this->assertFalse($auth->user_login('learner@example.com', 'wrong-password'));

        // The email is now taken.
        $this->assertFalse(\auth_flexaccess\api::email_available('learner@example.com'));
    }

    /**
     * Quick registration is refused when the policy does not allow it.
     *
     * @return void
     */
    public function test_quick_registration_requires_policy(): void {
        $this->resetAfterTest();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);

        $result = access_controller::grant_quick_registration((int) $course->id, (object) [
            'email' => 'nope@example.com',
            'firstname' => 'No',
            'lastname' => 'Policy',
            'password' => 'Str0ng-Pass!23',
        ]);
        $this->assertSame('notallowed', $result->status);
    }
}
