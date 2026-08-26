<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace enrol_flexaccess;


/**
 * Tests for the course-side entry (enrol_page_hook) offered to logged-in users.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_flexaccess_plugin::enrol_page_hook
 */
final class enrol_page_hook_test extends \advanced_testcase {
    /**
     * Build a course with an enabled FlexAccess instance offering normal login.
     *
     * @return array{0: \stdClass, 1: \stdClass} The course and the enrol instance record.
     */
    private function course_with_instance(): array {
        global $DB;
        set_config('allowwidening', 0, 'enrol_flexaccess');
        set_config('allownormallogin', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'allownormallogin' => 1,
        ]);
        $instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        return [$course, $instance];
    }

    public function test_offers_entry_to_logged_in_unenrolled_user(): void {
        $this->resetAfterTest();
        [$course, $instance] = $this->course_with_instance();
        $this->setUser($this->getDataGenerator()->create_user());

        $plugin = enrol_get_plugin('flexaccess');
        $html = $plugin->enrol_page_hook($instance);
        $this->assertNotEmpty($html, 'A logged-in unenrolled user must see a course-side entry.');
        $this->assertStringContainsString('flexaccessenrol', $html);
    }

    public function test_no_entry_for_guest(): void {
        $this->resetAfterTest();
        [$course, $instance] = $this->course_with_instance();
        $this->setGuestUser();

        $plugin = enrol_get_plugin('flexaccess');
        $this->assertSame('', $plugin->enrol_page_hook($instance));
    }

    public function test_no_entry_when_normal_login_not_offered(): void {
        $this->resetAfterTest();
        global $DB;
        [$course, $instance] = $this->course_with_instance();
        // Withdraw normal login at instance level.
        \enrol_flexaccess\local\instance_config::save((int) $instance->id, ['allownormallogin' => 0]);
        $this->setUser($this->getDataGenerator()->create_user());

        $plugin = enrol_get_plugin('flexaccess');
        $this->assertSame('', $plugin->enrol_page_hook($instance));
    }

    public function test_no_entry_for_already_enrolled_user(): void {
        $this->resetAfterTest();
        [$course, $instance] = $this->course_with_instance();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, null, 'flexaccess');
        $this->setUser($user);

        $plugin = enrol_get_plugin('flexaccess');
        $this->assertSame('', $plugin->enrol_page_hook($instance));
    }
}
