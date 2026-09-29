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
 * Uninstall removes what FlexAccess created outside its own tables (Lessons Learnt 27).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class uninstall_test extends \advanced_testcase {
    /**
     * Load the uninstall hook.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/enrol/flexaccess/db/uninstall.php');
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
    }

    /**
     * Course roles of FlexAccess enrolments and both roles are gone; nothing else is touched.
     *
     * @return void
     */
    public function test_removes_flexaccess_roles_and_assignments(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->assertTrue(local\enrol_service::admin_enrol((int) $course->id, (int) $user->id));
        participant_role::restrict((int) $user->id);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $participant = participant_role::get_id();
        $this->assertTrue($DB->record_exists('role_assignments', ['roleid' => $participant, 'userid' => $user->id]));

        xmldb_enrol_flexaccess_uninstall();

        $this->assertFalse($DB->record_exists('role', ['shortname' => participant_role::SHORTNAME]));
        $this->assertFalse($DB->record_exists('role', ['shortname' => participant_role::RESTRICTION_SHORTNAME]));
        $this->assertFalse($DB->record_exists('role_assignments', ['roleid' => $participant]));
        // An unrelated enrolment keeps its role.
        $this->assertTrue(user_has_role_assignment(
            (int) $student->id,
            $DB->get_field('role', 'id', ['shortname' => 'student']),
            \context_course::instance((int) $course->id)->id
        ));
    }

    /**
     * An administrator's own use of the participant role is not revoked: the role then stays.
     *
     * @return void
     */
    public function test_keeps_participant_role_used_elsewhere(): void {
        global $DB;
        participant_role::ensure();
        $participant = participant_role::get_id();
        $othercourse = $this->getDataGenerator()->create_course();
        $manual = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $manual->id, (int) $othercourse->id, $participant, 'manual');

        xmldb_enrol_flexaccess_uninstall();

        $this->assertTrue($DB->record_exists('role', ['id' => $participant]));
        $othercontext = \context_course::instance((int) $othercourse->id);
        $this->assertTrue(user_has_role_assignment((int) $manual->id, $participant, $othercontext->id));
        $this->assertFalse($DB->record_exists('role', ['shortname' => participant_role::RESTRICTION_SHORTNAME]));
    }
}
