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
 * Tests that a temporary user can be persisted while keeping the same user id and enrolment.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \auth_flexaccess\api
 */
final class persistence_test extends \advanced_testcase {
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
     * Temporary access, once persisted, keeps the same user id, enrolment and becomes loginnable.
     *
     * @return void
     */
    public function test_temporary_access_persists_and_keeps_enrolment(): void {
        $this->resetAfterTest();

        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        instance_config::save($enrolid, ['allowtemporary' => 1]);

        // Anonymous temporary entry: a temporary, enrolled account.
        $granted = access_controller::grant_temporary_access((int) $course->id);
        $this->assertSame('granted', $granted->status);
        $userid = (int) $granted->userid;
        $context = \context_course::instance((int) $course->id);
        $this->assertTrue(is_enrolled($context, \core_user::get_user($userid)));
        $this->assertTrue(\auth_flexaccess\local\account_service::is_temporary($userid));

        // The user makes the account permanent.
        $status = \auth_flexaccess\api::persist_temporary_user(
            $userid,
            'kept@example.com',
            'Kept',
            'Learner',
            'Str0ng-Pass!23'
        );
        $this->assertSame('converted', $status);

        // Same user id: still enrolled, no longer temporary, and now able to log in.
        $this->assertFalse(\auth_flexaccess\local\account_service::is_temporary($userid));
        $this->assertTrue(is_enrolled($context, \core_user::get_user($userid)));

        $refreshed = \core_user::get_user($userid);
        $this->assertSame('kept@example.com', $refreshed->email);
        $this->assertSame('kept@example.com', $refreshed->username);
        $this->assertEquals(0, (int) $refreshed->emailstop);

        $auth = get_auth_plugin('flexaccess');
        $this->assertTrue($auth->user_login('kept@example.com', 'Str0ng-Pass!23'));
        $this->assertFalse(\auth_flexaccess\api::email_available('kept@example.com'));
    }

    /**
     * Persistence is refused for an account that is not a temporary FlexAccess account.
     *
     * @return void
     */
    public function test_persist_rejects_non_temporary(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $status = \auth_flexaccess\api::persist_temporary_user(
            (int) $user->id,
            'other@example.com',
            'Other',
            'Person',
            'Str0ng-Pass!23'
        );
        $this->assertSame('nottemporary', $status);
    }
}
