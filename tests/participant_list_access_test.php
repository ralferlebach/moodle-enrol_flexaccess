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

use enrol_flexaccess\local\participant_role;
use enrol_flexaccess\local\participant_list_access;

/**
 * Tests that participant-list visibility is actually enforced on the dedicated role.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_flexaccess\local\participant_list_access
 */
final class participant_list_access_test extends \advanced_testcase {
    /**
     * Hiding prevents a FlexAccess participant from viewing the roster; showing restores it.
     *
     * @return void
     */
    public function test_hide_then_show_toggles_view_capability(): void {
        $this->resetAfterTest();
        $roleid = participant_role::ensure();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();
        role_assign($roleid, (int) $user->id, $context->id, 'enrol_flexaccess', 1);

        // Default (show): the participant may view the roster, matching the core page gate.
        participant_list_access::sync((int) $course->id, 'show');
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability('moodle/course:viewparticipants', $context, $user));

        // Hide: both roster-gating capabilities are prevented, so the core gate refuses.
        participant_list_access::sync((int) $course->id, 'hide');
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability('moodle/course:viewparticipants', $context, $user));
        $this->assertFalse(has_capability('moodle/course:enrolreview', $context, $user));

        // Back to show: the override is removed and viewing works again.
        participant_list_access::sync((int) $course->id, 'show');
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability('moodle/course:viewparticipants', $context, $user));
    }

    /**
     * The override is scoped to the course: a regular student elsewhere is unaffected.
     *
     * @return void
     */
    public function test_hide_does_not_affect_regular_students(): void {
        global $DB;
        $this->resetAfterTest();
        participant_role::ensure();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        participant_list_access::sync((int) $course->id, 'hide');
        accesslib_clear_all_caches_for_unit_testing();

        // A real student (not on the FlexAccess role) still sees participants.
        $this->assertTrue(has_capability('moodle/course:viewparticipants', $context, $student));
    }

    /**
     * Changing the system-level default must reach an existing instance without a re-save: a visitor
     * enrolled while the default was "show" must lose roster access after it flips to "hide".
     *
     * @return void
     */
    public function test_system_default_change_resyncs_existing_instance(): void {
        $this->resetAfterTest();
        set_config('participantlistaccessdefault', 'show', 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'allowtemporary' => 1,
            'participantlistaccess' => 'inherit',
        ]);
        $user = $this->getDataGenerator()->create_user();
        \enrol_flexaccess\local\enrol_service::enrol_with_capacity((int) $enrolid, (int) $user->id);
        $context = \context_course::instance((int) $course->id);

        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(
            has_capability('moodle/course:viewparticipants', $context, $user),
            'Baseline: visible while the default is show.'
        );

        // Flip the system default only, then resync (as the settings update callback does).
        set_config('participantlistaccessdefault', 'hide', 'enrol_flexaccess');
        participant_list_access::resync_all();

        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(
            has_capability('moodle/course:viewparticipants', $context, $user),
            'The system default change must reach the existing instance.'
        );
        $this->assertFalse(has_capability('moodle/course:enrolreview', $context, $user));
    }
}
