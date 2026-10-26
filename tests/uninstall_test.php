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

    /**
     * The participant role assignment FlexAccess made carries its component and instance (provenance).
     *
     * @return void
     */
    public function test_flexaccess_assignment_carries_its_provenance(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->assertTrue(local\enrol_service::admin_enrol((int) $course->id, (int) $user->id));
        $enrolid = (int) $DB->get_field('enrol', 'id', ['courseid' => $course->id, 'enrol' => 'flexaccess']);
        $ra = $DB->get_record('role_assignments', [
            'roleid' => participant_role::get_id(),
            'userid' => $user->id,
            'contextid' => \context_course::instance((int) $course->id)->id,
        ], '*', MUST_EXIST);
        $this->assertSame('enrol_flexaccess', $ra->component);
        $this->assertEquals($enrolid, $ra->itemid);
    }

    /**
     * Same user, same course, same role: the FlexAccess assignment goes, the administrator's own stays
     * (and with it the role); other roles and the manual enrolment are untouched; a second run changes nothing.
     *
     * @return void
     */
    public function test_keeps_manual_participant_role_in_the_same_course(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance((int) $course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->assertTrue(local\enrol_service::admin_enrol((int) $course->id, (int) $user->id));
        $participant = participant_role::get_id();
        $student = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        // The administrator also enrols the person manually and gives the participant role by hand.
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id, $student, 'manual');
        role_assign($participant, (int) $user->id, $context->id);
        $this->assertSame(2, $DB->count_records('role_assignments', ['roleid' => $participant, 'userid' => $user->id]));

        xmldb_enrol_flexaccess_uninstall();

        $this->assertFalse($DB->record_exists('role_assignments', ['roleid' => $participant, 'component' => 'enrol_flexaccess']));
        $this->assertTrue($DB->record_exists('role_assignments', [
            'roleid' => $participant, 'userid' => $user->id, 'contextid' => $context->id, 'component' => '',
        ]), 'The manual assignment stays.');
        $this->assertTrue($DB->record_exists('role', ['id' => $participant]), 'The role is still in use.');
        $this->assertTrue(user_has_role_assignment((int) $user->id, $student, $context->id));
        $this->assertTrue(is_enrolled($context, (int) $user->id), 'The manual enrolment is untouched.');

        xmldb_enrol_flexaccess_uninstall();
        $this->assertTrue($DB->record_exists('role_assignments', ['roleid' => $participant, 'component' => '']));
    }

    /**
     * Assignments without component from releases before 2026102502: the upgrade attributes those it can (the
     * person has only the FlexAccess enrolment in the course) and keeps the others; uninstall then removes
     * exactly the attributed ones.
     *
     * @return void
     */
    public function test_upgrade_attributes_legacy_assignments_conservatively(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/enrol/flexaccess/db/upgrade.php');
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance((int) $course->id);
        $participant = participant_role::ensure();
        $only = $this->getDataGenerator()->create_user();
        $both = $this->getDataGenerator()->create_user();
        foreach ([$only, $both] as $user) {
            $this->assertTrue(local\enrol_service::admin_enrol((int) $course->id, (int) $user->id));
        }
        $this->getDataGenerator()->enrol_user((int) $both->id, (int) $course->id, null, 'manual');
        // Simulate the state of a release before 2026102502: the assignments carry no component.
        $DB->set_field('role_assignments', 'component', '', ['roleid' => $participant, 'contextid' => $context->id]);
        $DB->set_field('role_assignments', 'itemid', 0, ['roleid' => $participant, 'contextid' => $context->id]);

        set_config('version', 2026102501, 'enrol_flexaccess');
        $this->assertTrue(xmldb_enrol_flexaccess_upgrade(2026102501));
        $rows = fn(int $userid): array => array_values(array_map(
            static fn($ra) => $ra->component,
            $DB->get_records('role_assignments', ['roleid' => $participant, 'userid' => $userid], 'component ASC')
        ));
        $this->assertSame(['enrol_flexaccess'], $rows((int) $only->id), 'Only FlexAccess: attributed.');
        $this->assertSame(['', 'enrol_flexaccess'], $rows((int) $both->id), 'Another enrolment: the old one is kept.');
        // Idempotent.
        set_config('version', 2026102501, 'enrol_flexaccess');
        $this->assertTrue(xmldb_enrol_flexaccess_upgrade(2026102501));
        $this->assertSame(['', 'enrol_flexaccess'], $rows((int) $both->id));

        xmldb_enrol_flexaccess_uninstall();
        $this->assertSame([], $rows((int) $only->id));
        $this->assertSame([''], $rows((int) $both->id));
    }

    /**
     * The declared dependencies of the family form no cycle: Moodle refuses to uninstall any plugin that another
     * plugin requires, so a cycle (auth <-> enrol until 2026102501) would make both impossible to remove and
     * xmldb_enrol_flexaccess_uninstall() would never run through Moodle.
     */
    public function test_no_dependency_cycle_blocks_uninstall(): void {
        $pluginman = \core_plugin_manager::instance();
        $family = ['auth_flexaccess', 'enrol_flexaccess', 'mod_flexaccess', 'tool_flexaccess'];
        $edges = [];
        foreach ($family as $component) {
            $info = $pluginman->get_plugin_info($component);
            if (!$info) {
                $this->markTestSkipped("$component is not installed.");
            }
            $edges[$component] = array_values(array_intersect(array_keys((array) $info->get_other_required_plugins()), $family));
        }
        // Remove plugins nobody requires, one after the other - the uninstall order Moodle allows.
        $order = [];
        while ($edges) {
            $required = array_merge(...array_values($edges));
            $free = array_values(array_diff(array_keys($edges), $required));
            $this->assertNotEmpty($free, 'Dependency cycle among: ' . implode(', ', array_keys($edges)));
            foreach ($free as $component) {
                $order[] = $component;
                unset($edges[$component]);
            }
        }
        $this->assertSame(['auth_flexaccess'], array_slice($order, -1));
        $this->assertLessThan(array_search('auth_flexaccess', $order), array_search('enrol_flexaccess', $order));
    }
}
