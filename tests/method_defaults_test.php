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

use enrol_flexaccess\local\policy_assembler;

/**
 * Tests for the system-level access-method default ceiling and its interaction with widening.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_flexaccess\local\policy_assembler
 */
final class method_defaults_test extends \advanced_testcase {
    /**
     * Create an enabled FlexAccess instance offering temporary access in a fresh course.
     *
     * @return int Course id.
     */
    private function course_with_temporary_instance(): int {
        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');
        $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'allowtemporary' => 1,
            'allownormallogin' => 1,
        ]);
        return (int) $course->id;
    }

    /**
     * With no system default and widening off, an instance's temporary checkbox is neutralised.
     * This is the reproduction of the login-blocker: reverting the system_policy wiring must keep
     * this green while the two tests below turn red.
     */
    public function test_instance_method_neutralised_by_default(): void {
        $this->resetAfterTest();
        set_config('allowwidening', 0, 'enrol_flexaccess');
        $courseid = $this->course_with_temporary_instance();

        $policy = api::get_effective_policy($courseid);
        $this->assertFalse($policy->allowtemporary, 'Instance temporary must be neutralised by the off-by-default ceiling.');
        $this->assertFalse(api::offers_anonymous_entry($courseid));
    }

    /**
     * Turning on the system-level default lets the instance checkbox take effect.
     */
    public function test_system_default_enables_method(): void {
        $this->resetAfterTest();
        set_config('allowwidening', 0, 'enrol_flexaccess');
        set_config('allowtemporary', 1, 'enrol_flexaccess');
        $courseid = $this->course_with_temporary_instance();

        $policy = api::get_effective_policy($courseid);
        $this->assertTrue($policy->allowtemporary);
        $this->assertTrue(api::offers_anonymous_entry($courseid));
    }

    /**
     * Alternatively, enabling widening lets a lower scope re-enable a method the ceiling omits.
     */
    public function test_widening_enables_method(): void {
        $this->resetAfterTest();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $courseid = $this->course_with_temporary_instance();

        $policy = api::get_effective_policy($courseid);
        $this->assertTrue($policy->allowtemporary);
        $this->assertTrue(api::offers_anonymous_entry($courseid));
    }

    /**
     * The ceiling reflects system defaults without the course instance contribution.
     */
    public function test_ceiling_excludes_instance(): void {
        $this->resetAfterTest();
        set_config('allowwidening', 0, 'enrol_flexaccess');
        set_config('allowtemporary', 0, 'enrol_flexaccess');
        $courseid = $this->course_with_temporary_instance();

        $ceiling = policy_assembler::ceiling($courseid);
        $this->assertFalse($ceiling->allowtemporary, 'Ceiling must ignore the instance and follow the system default.');
    }
}
