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
 * Tests for FlexAccess effective-policy assembly and facade.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use PHPUnit\Framework\Attributes\CoversClass;
use enrol_flexaccess\local\policy_assembler;

/**
 * Policy assembler and API facade tests.
 *
 * @package    enrol_flexaccess
 */
#[CoversClass(\enrol_flexaccess\local\policy_assembler::class)]
final class policy_assembler_test extends \advanced_testcase {
    /**
     * System policy reads participant-visibility and access-key defaults from config.
     */
    public function test_system_policy_from_config(): void {
        $this->resetAfterTest();
        set_config('participantlistaccessdefault', 'hide', 'enrol_flexaccess');
        set_config('temporaryaccesskeyrequired', 1, 'enrol_flexaccess');
        $p = policy_assembler::system_policy();
        $this->assertSame('hide', $p->participantlistaccess);
        $this->assertTrue($p->temporaryaccesskeyrequired);
    }

    /**
     * Assembling a course with an instance applies the instance window/capacity.
     */
    public function test_assemble_applies_instance(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');
        $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'availablefrom' => 1000,
            'availableuntil' => 2000,
            'maxparticipants' => 25,
        ]);

        $this->assertTrue(\enrol_flexaccess\api::is_target_enabled((int) $course->id));
        $p = \enrol_flexaccess\api::get_effective_policy((int) $course->id);
        $this->assertSame(1000, $p->availablefrom);
        $this->assertSame(2000, $p->availableuntil);
        $this->assertSame(25, $p->maxparticipants);
    }

    /**
     * A course without a FlexAccess instance is not an enabled target.
     */
    public function test_target_not_enabled_without_instance(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse(\enrol_flexaccess\api::is_target_enabled((int) $course->id));
        // A policy is still returned (identity-independent, methods default off / login on).
        $p = \enrol_flexaccess\api::get_effective_policy((int) $course->id);
        $this->assertTrue($p->allownormallogin);
        $this->assertFalse($p->allowtemporary);
    }

    /**
     * Active enrolment count is summed across enabled instances.
     */
    public function test_active_enrolment_count(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        $instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        for ($i = 0; $i < 3; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $plugin->enrol_user($instance, $user->id, null, 0, 0, ENROL_USER_ACTIVE);
        }
        $this->assertSame(3, \enrol_flexaccess\api::get_active_enrolment_count((int) $course->id));
    }
}
