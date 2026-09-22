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

use enrol_flexaccess\local\enrolment_admin;
use enrol_flexaccess\local\participant_role;
use enrol_flexaccess\local\readiness;

/**
 * Tests for the 1.1.0 enrol additions: access-list icon, readiness and enrolment administration.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_flexaccess\local\readiness
 * @covers \enrol_flexaccess\local\enrolment_admin
 * @covers \enrol_flexaccess_plugin
 */
final class readiness_test extends \advanced_testcase {
    /**
     * Issue enrol#5: the access-list action uses a core list icon, not the users icon.
     *
     * @return void
     */
    public function test_accesslist_icon_is_a_core_list_icon(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/enrol/flexaccess/lib.php');
        $this->assertNotSame('i/users', \enrol_flexaccess_plugin::ACCESSLISTS_ICON);
        $this->assertSame('e/bullet_list', \enrol_flexaccess_plugin::ACCESSLISTS_ICON);
        // Available in this Moodle version as a core icon (font map), no plugin-local SVG.
        $map = \core\output\icon_system::instance(\core\output\icon_system::FONTAWESOME)->get_icon_name_map();
        $this->assertArrayHasKey('core:' . \enrol_flexaccess_plugin::ACCESSLISTS_ICON, $map);
        $this->assertStringContainsString('fa-list', $map['core:' . \enrol_flexaccess_plugin::ACCESSLISTS_ICON]);
        $localcopy = $CFG->dirroot . '/enrol/flexaccess/pix/' . basename(\enrol_flexaccess_plugin::ACCESSLISTS_ICON) . '.svg';
        $this->assertFileDoesNotExist($localcopy);
        // The accessible name stays "Access lists".
        $icon = new \pix_icon(\enrol_flexaccess_plugin::ACCESSLISTS_ICON, get_string('accesslists', 'enrol_flexaccess'));
        $html = $PAGE->get_renderer('core')->render($icon);
        $this->assertStringContainsString(get_string('accesslists', 'enrol_flexaccess'), $html);
    }

    /**
     * A fresh install has a correct role model; broken definitions are detected and repaired.
     *
     * @return void
     */
    public function test_role_model_problems_and_repair(): void {
        global $DB;
        $this->resetAfterTest();
        participant_role::ensure();
        $this->assertSame([], readiness::role_model_problems());

        $participant = participant_role::get_id();
        $restriction = participant_role::get_restriction_id();
        set_role_contextlevels($participant, [CONTEXT_COURSE, CONTEXT_SYSTEM]);
        role_assign($participant, $this->getDataGenerator()->create_user()->id, \context_system::instance()->id);
        assign_capability('moodle/site:sendmessage', CAP_ALLOW, $restriction, \context_system::instance()->id, true);
        assign_capability('moodle/course:view', CAP_ALLOW, $restriction, \context_system::instance()->id, true);
        $problems = readiness::role_model_problems();
        foreach (['participantcontext', 'participantsystemassigned', 'restrictionpositive', 'restrictionprohibit'] as $code) {
            $this->assertContains($code, $problems);
        }
        $this->assertSame(1, enrolment_admin::find_role_mismatches()['systemparticipant']);

        readiness::repair_role_model();
        $problems = readiness::role_model_problems();
        $this->assertNotContains('participantcontext', $problems);
        $this->assertNotContains('restrictionprohibit', $problems);

        $DB->delete_records('role', ['id' => $restriction]);
        $this->assertContains('restrictionmissing', readiness::role_model_problems());
    }

    /**
     * A course method that the site ceiling or the magic master switch neutralises is reported.
     *
     * @return void
     */
    public function test_policy_conflicts(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $enrolid = local\enrol_service::ensure_instance((int) $course->id);
        $DB->set_field('enrol_flexaccess_instance', 'allowtemporary', 1, ['enrolid' => $enrolid]);
        $DB->set_field('enrol_flexaccess_instance', 'allowmagiclogin', 1, ['enrolid' => $enrolid]);
        set_config('allowtemporary', 0, 'enrol_flexaccess');
        set_config('allowmagiclogin', 1, 'enrol_flexaccess');
        set_config('allowmagiclogin', 0, 'auth_flexaccess');
        $conflicts = readiness::policy_conflicts((int) $course->id);
        $found = array_map(static fn($c) => $c->flag . '/' . $c->cause, $conflicts);
        $this->assertContains('allowtemporary/ceiling', $found);
        if (class_exists('\auth_flexaccess\api')) {
            $this->assertContains('allowmagiclogin/magicmaster', $found);
        }
        // The compact course verdict names them, and no conflict when the ceiling allows it.
        $this->assertContains('policy_allowtemporary_ceiling', readiness::course_problems((int) $course->id));
        set_config('allowtemporary', 1, 'enrol_flexaccess');
        $found = array_map(static fn($c) => $c->flag, readiness::policy_conflicts((int) $course->id));
        $this->assertNotContains('allowtemporary', $found);
    }

    /**
     * Enrolments created through FlexAccess are not reported as lacking their course role.
     *
     * @return void
     */
    public function test_role_mismatch_has_no_false_positives(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->assertTrue(local\enrol_service::admin_enrol((int) $course->id, (int) $user->id));
        $this->assertSame([], enrolment_admin::find_role_mismatches()['missingrole']);
        // Removing the role makes it a real finding.
        role_unassign(participant_role::get_id(), (int) $user->id, \context_course::instance((int) $course->id)->id);
        $this->assertCount(1, enrolment_admin::find_role_mismatches()['missingrole']);
    }

    /**
     * Reactivation is scoped to a course and renews an already passed end time.
     *
     * @return void
     */
    public function test_reactivation_scoped_and_renewed(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        local\enrol_service::admin_enrol((int) $course->id, (int) $user->id);
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'flexaccess']);
        enrol_get_plugin('flexaccess')->update_user_enrol($instance, (int) $user->id, ENROL_USER_SUSPENDED, null, time() - 10);
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);

        // Out of scope: a teacher of another course cannot touch it.
        $this->assertNull(enrolment_admin::reactivate((int) $ue->id, (int) $other->id));
        $change = enrolment_admin::reactivate((int) $ue->id, (int) $course->id);
        $this->assertNotNull($change);
        $after = $DB->get_record('user_enrolments', ['id' => $ue->id]);
        $this->assertEquals(ENROL_USER_ACTIVE, $after->status);
        // Without an enrolment period the passed end is removed, so expiry does not suspend it again.
        $this->assertEquals(0, $after->timeend);
        local\enrol_expiry::process();
        $this->assertEquals(ENROL_USER_ACTIVE, $DB->get_field('user_enrolments', 'status', ['id' => $ue->id]));
        // Nothing to do for an active enrolment.
        $this->assertNull(enrolment_admin::reactivate((int) $ue->id, (int) $course->id));
        $snapshot = enrolment_admin::get_user_enrolments((int) $user->id, (int) $course->id);
        $this->assertCount(1, $snapshot);
        $this->assertSame([], enrolment_admin::get_user_enrolments((int) $user->id, (int) $other->id));
    }
}
