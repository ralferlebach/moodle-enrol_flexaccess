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

use enrol_flexaccess\local\access_controller;
use enrol_flexaccess\local\instance_config;

/**
 * An access key must only be demanded when one is actually configured: "no key set" must never
 * result in "key required" (which would lock every temporary user out).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\enrol_flexaccess\local\access_key_service::class)]
final class access_key_unset_test extends \advanced_testcase {
    /**
     * Create a fresh course-key-mode instance that has no key configured.
     *
     * @return array{0:int,1:int} Course id and enrol instance id.
     */
    private function course_mode_instance_without_key(): array {
        global $DB;
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        instance_config::save($enrolid, ['allowtemporary' => 1, 'temporarylifetime' => DAYSECS]);
        // Course-key mode selected, but no key hash was ever entered.
        $DB->set_field('enrol_flexaccess_instance', 'temporaryaccesskeymode', 'course', ['enrolid' => $enrolid]);
        \cache::make('enrol_flexaccess', 'policy')->purge();
        return [(int) $course->id, (int) $enrolid];
    }

    public function test_unset_key_is_not_required_and_does_not_block(): void {
        $this->resetAfterTest();
        [$courseid] = $this->course_mode_instance_without_key();

        $this->assertFalse(\enrol_flexaccess\api::requires_temporary_access_key($courseid));
        // The grant must not be blocked by a phantom key gate.
        $this->assertNotSame('badkey', access_controller::grant_temporary_access($courseid)->status);
    }

    public function test_key_is_required_once_configured(): void {
        global $DB;
        $this->resetAfterTest();
        [$courseid, $enrolid] = $this->course_mode_instance_without_key();
        $DB->set_field(
            'enrol_flexaccess_instance',
            'temporaryaccesskeyhash',
            password_hash('OPEN-SESAME', PASSWORD_DEFAULT),
            ['enrolid' => $enrolid]
        );
        \cache::make('enrol_flexaccess', 'policy')->purge();

        $this->assertTrue(\enrol_flexaccess\api::requires_temporary_access_key($courseid));
        $this->assertSame('badkey', access_controller::grant_temporary_access($courseid)->status);
    }
}
