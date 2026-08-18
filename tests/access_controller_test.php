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

/**
 * Tests for the FlexAccess temporary-access controller.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use enrol_flexaccess\local\access_controller;

/**
 * Access controller tests.
 *
 * @package    enrol_flexaccess
 * @covers     \enrol_flexaccess\local\access_controller
 */
final class access_controller_test extends \advanced_testcase {
    /**
     * Create a course with an enabled FlexAccess instance that offers temporary access.
     *
     * @param int $max Maximum participants (0 = unlimited).
     * @param int $from Window start.
     * @param int $until Window end.
     * @return \stdClass Course record.
     */
    private function course_with_instance(int $max = 0, int $from = 0, int $until = 0): \stdClass {
        global $DB;
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        $DB->set_field('enrol_flexaccess_instance', 'allowtemporary', 1, ['enrolid' => $enrolid]);
        $DB->set_field('enrol_flexaccess_instance', 'temporarylifetime', DAYSECS, ['enrolid' => $enrolid]);
        if ($max > 0) {
            $DB->set_field('enrol_flexaccess_instance', 'maxparticipants', $max, ['enrolid' => $enrolid]);
        }
        if ($from > 0 || $until > 0) {
            $DB->set_field('enrol_flexaccess_instance', 'availablefrom', $from, ['enrolid' => $enrolid]);
            $DB->set_field('enrol_flexaccess_instance', 'availableuntil', $until, ['enrolid' => $enrolid]);
        }
        return $course;
    }

    /**
     * A visitor is granted temporary access: a user is created, enrolled and followed up.
     */
    public function test_grant_success(): void {
        global $DB;
        $this->resetAfterTest();
        $sink = $this->redirectEmails();
        $now = 1000000;
        $course = $this->course_with_instance();

        $result = access_controller::grant_temporary_access((int) $course->id, $now);
        $this->assertSame('granted', $result->status);
        $this->assertGreaterThan(0, $result->userid);
        $this->assertSame(
            \auth_flexaccess\local\account_type::TEMPORARY_USER,
            \auth_flexaccess\api::classify_user($result->userid)
        );
        $this->assertTrue($DB->record_exists(
            'user_enrolments',
            ['enrolid' => $result->enrolid, 'userid' => $result->userid]
        ));
        $this->assertEquals(1, $DB->count_records(
            'auth_flexaccess_mailqueue',
            ['userid' => $result->userid, 'status' => 'queued']
        ));
        $sink->close();
    }

    /**
     * Access outside the window is refused before creating anything.
     */
    public function test_closed_window(): void {
        global $DB;
        $this->resetAfterTest();
        $now = 5000;
        $course = $this->course_with_instance(0, 1000, 2000);
        $before = $DB->count_records('user');
        $result = access_controller::grant_temporary_access((int) $course->id, $now);
        $this->assertSame('closed', $result->status);
        $this->assertSame($before, $DB->count_records('user'));
    }

    /**
     * A full instance refuses further grants.
     */
    public function test_full_capacity(): void {
        $this->resetAfterTest();
        $sink = $this->redirectEmails();
        $now = 1000000;
        $course = $this->course_with_instance(1);
        $first = access_controller::grant_temporary_access((int) $course->id, $now);
        $this->assertSame('granted', $first->status);
        $second = access_controller::grant_temporary_access((int) $course->id, $now);
        $this->assertSame('full', $second->status);
        $sink->close();
    }
}
