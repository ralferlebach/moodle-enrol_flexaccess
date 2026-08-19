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

/**
 * Tests for the dedicated FlexAccess participant role.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_flexaccess\local\participant_role
 */
final class participant_role_test extends \advanced_testcase {
    /**
     * restrict() prevents messaging and profile editing site-wide; unrestrict() restores them.
     *
     * @return void
     */
    public function test_restrict_and_unrestrict_site_capabilities(): void {
        $this->resetAfterTest();
        participant_role::ensure();
        $user = $this->getDataGenerator()->create_user();
        $system = \context_system::instance();

        // Baseline: a normal authenticated user may message and edit their own profile.
        $this->assertTrue(has_capability('moodle/site:sendmessage', $system, $user));

        participant_role::restrict((int) $user->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability('moodle/site:sendmessage', $system, $user));
        $this->assertFalse(has_capability('moodle/user:editownprofile', $system, $user));

        participant_role::unrestrict((int) $user->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability('moodle/site:sendmessage', $system, $user));
    }

    /**
     * ensure() creates a course-level, student-archetype role that can view participants by default.
     *
     * @return void
     */
    public function test_ensure_creates_role(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = participant_role::ensure();
        $this->assertGreaterThan(0, $roleid);

        $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
        $this->assertSame(participant_role::SHORTNAME, $role->shortname);
        $this->assertSame('student', $role->archetype);

        $levels = array_map('intval', $DB->get_fieldset_select('role_context_levels', 'contextlevel', 'roleid = ?', [$roleid]));
        sort($levels);
        $this->assertSame([CONTEXT_SYSTEM, CONTEXT_COURSE], $levels);

        // Student archetype defaults let the role view participants (so 'show' is a no-op).
        $system = \context_system::instance();
        $this->assertTrue(has_capability('moodle/course:viewparticipants', $system, $this->make_holder($roleid, $system)));
    }

    /**
     * ensure() is idempotent: repeated calls return the same role and create no duplicate.
     *
     * @return void
     */
    public function test_ensure_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $first = participant_role::ensure();
        $second = participant_role::ensure();
        $this->assertSame($first, $second);
        $this->assertSame(1, $DB->count_records('role', ['shortname' => participant_role::SHORTNAME]));
    }

    /**
     * migrate_existing() reassigns FlexAccess-component role assignments to the dedicated role.
     *
     * @return void
     */
    public function test_migrate_existing(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();
        $studentid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);

        // Simulate a pre-upgrade FlexAccess enrolment assignment on the student role.
        role_assign($studentid, (int) $user->id, $context->id, 'enrol_flexaccess', 77);

        $migrated = participant_role::migrate_existing();
        $this->assertSame(1, $migrated);

        $dedicated = participant_role::get_id();
        $this->assertFalse($DB->record_exists('role_assignments', [
            'userid' => $user->id, 'roleid' => $studentid, 'component' => 'enrol_flexaccess',
        ]));
        $this->assertTrue($DB->record_exists('role_assignments', [
            'userid' => $user->id, 'roleid' => $dedicated, 'component' => 'enrol_flexaccess', 'itemid' => 77,
        ]));
    }

    /**
     * Assign a role to a fresh user in a context and return that user.
     *
     * @param int $roleid Role id.
     * @param \context $context Context to assign in.
     * @return \stdClass User record.
     */
    private function make_holder(int $roleid, \context $context): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        role_assign($roleid, (int) $user->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $user;
    }
}
