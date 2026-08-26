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
use enrol_flexaccess\local\access_key_rate;
use enrol_flexaccess\local\instance_config;

/**
 * Tests for access-key enforcement in the grant flow and the brute-force limiter.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_flexaccess\local\access_key_service
 */
final class access_key_test extends \advanced_testcase {
    /**
     * Create a course with a temporary-access instance protected by a course access key.
     *
     * @param string $key Clear-text key to store (hashed).
     * @return \stdClass Course record.
     */
    private function course_with_key(string $key): \stdClass {
        global $DB;
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        instance_config::save($enrolid, ['allowtemporary' => 1, 'temporarylifetime' => DAYSECS]);
        $DB->set_field('enrol_flexaccess_instance', 'temporaryaccesskeymode', 'course', ['enrolid' => $enrolid]);
        $DB->set_field(
            'enrol_flexaccess_instance',
            'temporaryaccesskeyhash',
            password_hash($key, PASSWORD_DEFAULT),
            ['enrolid' => $enrolid]
        );
        return $course;
    }

    /**
     * The grant is refused without a key, refused with a wrong key, and succeeds with the right key.
     *
     * @return void
     */
    public function test_grant_enforces_access_key(): void {
        $this->resetAfterTest();
        $course = $this->course_with_key('OPEN-SESAME');

        $this->assertSame('badkey', access_controller::grant_temporary_access((int) $course->id)->status);
        $this->assertSame('badkey', access_controller::grant_temporary_access((int) $course->id, null, 'wrong')->status);

        $result = access_controller::grant_temporary_access((int) $course->id, null, 'OPEN-SESAME');
        $this->assertSame('granted', $result->status);
        $this->assertGreaterThan(0, $result->userid);
    }

    /**
     * A course without a key requirement grants without one.
     *
     * @return void
     */
    public function test_grant_without_key_requirement(): void {
        $this->resetAfterTest();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        instance_config::save($enrolid, ['allowtemporary' => 1, 'temporarylifetime' => DAYSECS]);

        $this->assertSame('granted', access_controller::grant_temporary_access((int) $course->id)->status);
    }

    /**
     * The rate limiter blocks after the threshold and can be reset.
     *
     * @return void
     */
    public function test_rate_limiter(): void {
        $this->resetAfterTest();
        $id = access_key_rate::identifier('203.0.113.7', 42);
        $now = 1000000;

        for ($i = 0; $i < access_key_rate::MAX_ATTEMPTS; $i++) {
            $this->assertFalse(access_key_rate::is_blocked($id, $now));
            access_key_rate::record_failure($id, $now);
        }
        $this->assertTrue(access_key_rate::is_blocked($id, $now));

        // The window slides: far in the future the old failures no longer count.
        $this->assertFalse(access_key_rate::is_blocked($id, $now + access_key_rate::WINDOW + 1));

        access_key_rate::reset($id);
        $this->assertFalse(access_key_rate::is_blocked($id, $now));
    }

    /**
     * A course key entered through the instance form is hashed and then enforced by the grant flow.
     *
     * @return void
     */
    public function test_form_save_sets_course_key(): void {
        $this->resetAfterTest();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        instance_config::save($enrolid, [
            'allowtemporary' => 1,
            'temporarylifetime' => DAYSECS,
            'temporaryaccesskeymode' => 'course',
            'temporaryaccesskey' => 'FORM-KEY',
        ]);

        $this->assertSame('badkey', access_controller::grant_temporary_access((int) $course->id, null, 'nope')->status);
        $this->assertSame('granted', access_controller::grant_temporary_access((int) $course->id, null, 'FORM-KEY')->status);
    }
}
