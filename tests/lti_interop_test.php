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

use enrol_flexaccess\local\enrol_expiry;
use enrol_flexaccess\local\enrol_service;
use enrol_flexaccess\local\participant_role;

/**
 * FlexAccess and LTI (enrol_lti) enrolments of the same person coexist without changing each other
 * (enrol_flexaccess #10, isolated models; the cross-site journeys run in the two-site Playwright suite).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_flexaccess\local\enrol_expiry
 * @covers \enrol_flexaccess\local\enrolment_admin
 * @covers \enrol_flexaccess\local\enrolment_effective_state
 * @covers \enrol_flexaccess\local\lti_grade_visibility
 */
final class lti_interop_test extends \advanced_testcase {
    /** @var \frozen_clock The scenario's frozen Moodle clock: every reference time of a test reads it. */
    private \frozen_clock $clock;

    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Published LTI tool (helper::get_lti_tool() shape). */
    private \stdClass $tool;

    /** @var \context_course Course context. */
    private \context_course $context;

    /**
     * Course with FlexAccess and a published LTI 1.3 resource; FlexAccess and LTI enabled.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->clock = $this->mock_clock_with_frozen();
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,flexaccess,lti');
        $this->course = $this->getDataGenerator()->create_course();
        $this->context = \context_course::instance((int) $this->course->id);
        $this->assertGreaterThan(0, enrol_service::ensure_instance((int) $this->course->id));
        $tool = $this->getDataGenerator()->create_lti_tool((object) [
            'courseid' => $this->course->id,
            'ltiversion' => 'LTI-1p3',
        ]);
        $this->tool = \enrol_lti\helper::get_lti_tool((int) $tool->id);
    }

    /**
     * A person enrolled through FlexAccess and through the LTI resource.
     *
     * @param int $timeend FlexAccess enrolment end.
     * @return int User id.
     */
    private function dual(int $timeend = 0): int {
        global $DB;
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $this->assertTrue(enrol_service::admin_enrol((int) $this->course->id, $userid));
        if ($timeend > 0) {
            $DB->set_field_select(
                'user_enrolments',
                'timeend',
                $timeend,
                'userid = :u AND enrolid IN (SELECT id FROM {enrol} WHERE enrol = :e)',
                ['u' => $userid, 'e' => 'flexaccess']
            );
        }
        $this->assertSame(\enrol_lti\helper::ENROLMENT_SUCCESSFUL, \enrol_lti\helper::enrol_user($this->tool, $userid));
        return $userid;
    }

    /**
     * The status of the user's enrolment through a plugin in the course.
     *
     * @param int $userid User id.
     * @param string $plugin Enrolment plugin.
     * @return int|null
     */
    private function ue_status(int $userid, string $plugin): ?int {
        global $DB;
        $status = $DB->get_field_sql(
            "SELECT ue.status FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :u AND e.courseid = :c AND e.enrol = :p",
            ['u' => $userid, 'c' => $this->course->id, 'p' => $plugin]
        );
        return $status === false ? null : (int) $status;
    }

    /**
     * FlexAccess expiry suspends only the FlexAccess enrolment; the LTI access stays.
     */
    public function test_flexaccess_expiry_leaves_lti_enrolment(): void {
        $userid = $this->dual($this->clock->time() - 60);
        enrol_expiry::process();
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($userid, 'flexaccess'));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($userid, 'lti'));
        $this->assertTrue(is_enrolled($this->context, $userid, '', true), 'The LTI enrolment still grants access.');
        $state = api::effective_enrolment_state($userid, (int) $this->course->id);
        $this->assertSame(['user_enrolment_suspended', 'expired'], $state->problems);
    }

    /**
     * Ending the LTI membership leaves the FlexAccess enrolment, its role and its end time untouched.
     */
    public function test_lti_unenrol_leaves_flexaccess_enrolment(): void {
        global $DB;
        $end = $this->clock->time() + WEEKSECS;
        $userid = $this->dual($end);
        $instance = $DB->get_record('enrol', ['id' => $this->tool->enrolid]);
        enrol_get_plugin('lti')->unenrol_user($instance, $userid);
        $this->assertNull($this->ue_status($userid, 'lti'));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($userid, 'flexaccess'));
        $this->assertTrue(user_has_role_assignment($userid, participant_role::get_id(), $this->context->id));
        $state = api::effective_enrolment_state($userid, (int) $this->course->id);
        $this->assertSame('ok', $state->state);
        $this->assertSame($end, $state->timeend);
    }

    /**
     * FlexAccess reactivation never touches the LTI enrolment.
     */
    public function test_flexaccess_reactivation_leaves_lti_enrolment(): void {
        global $DB;
        $userid = $this->dual();
        $instance = $DB->get_record('enrol', ['id' => $this->tool->enrolid]);
        enrol_get_plugin('lti')->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
        $flexue = (int) $DB->get_field_sql(
            "SELECT ue.id FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :u AND e.enrol = 'flexaccess'",
            ['u' => $userid]
        );
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['id' => $flexue]);
        $this->assertSame('reactivated', api::reactivate_enrolment($flexue)->outcome);
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($userid, 'flexaccess'));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($userid, 'lti'), 'An intentionally suspended LTI access stays.');
    }

    /**
     * An LTI membership sync (re-enrolling the roster) does not repair an intentionally disabled FlexAccess access.
     */
    public function test_lti_enrol_does_not_repair_flexaccess(): void {
        global $DB;
        $userid = $this->dual();
        $flexinstance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'flexaccess']);
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['enrolid' => $flexinstance->id, 'userid' => $userid]);
        // What sync_members does for a roster member: enrol through the LTI resource again.
        \enrol_lti\helper::enrol_user($this->tool, $userid);
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($userid, 'flexaccess'));
        $this->assertSame('user_enrolment_suspended', api::effective_enrolment_state($userid, (int) $this->course->id)->state);
    }

    /**
     * Learner role of the resource plus FlexAccess role and restriction: intended effective capabilities.
     */
    public function test_effective_capabilities_of_dual_roles(): void {
        $userid = $this->dual();
        participant_role::restrict($userid);
        accesslib_clear_all_caches_for_unit_testing();
        // Course participation from either role.
        $this->assertTrue(has_capability('moodle/course:isincompletionreports', $this->context, $userid));
        // The FlexAccess restriction (system-level prohibit) wins over the LTI learner role.
        $this->assertFalse(has_capability('moodle/user:editownprofile', \context_system::instance(), $userid));
        $this->assertFalse(has_capability('moodle/site:sendmessage', \context_system::instance(), $userid));
    }

    /**
     * Grades stored for users who are not in the gradebook population are reported (received but invisible).
     */
    public function test_grades_received_but_invisible_are_reported(): void {
        global $DB;
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $this->assertTrue(enrol_service::admin_enrol((int) $this->course->id, $userid));
        $lti = $this->getDataGenerator()->create_module('lti', ['course' => $this->course->id, 'grade' => 100]);
        $item = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'lti',
            'iteminstance' => $lti->id,
            'courseid' => $this->course->id,
        ]);
        $DB->insert_record('grade_grades', (object) [
            'itemid' => $item->id,
            'userid' => $userid,
            'rawgrade' => 80,
            'finalgrade' => 80,
        ]);
        $this->assertSame(0, local\lti_grade_visibility::count_invisible());

        $ids = array_diff(participant_role::gradebook_roleids(), [participant_role::get_id()]);
        set_config('gradebookroles', implode(',', $ids));
        $this->assertSame(1, local\lti_grade_visibility::count_invisible());
        $found = local\lti_grade_visibility::find_invisible();
        $this->assertSame($userid, (int) $found[0]->userid);
        $this->assertSame((int) $this->course->id, (int) $found[0]->courseid);
    }

    /**
     * A FlexAccess enrolment at Core's rounding boundary is part of the population a launch and AGS rely on.
     */
    public function test_rounding_boundary_population(): void {
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $this->assertTrue(enrol_service::admin_enrol((int) $this->course->id, $userid));
        [$sql, $params] = get_enrolled_sql($this->context, '', 0, true);
        global $DB;
        $this->assertTrue($DB->record_exists_sql("SELECT 1 FROM ($sql) j WHERE j.id = :uid", $params + ['uid' => $userid]));
        $service = '\ltiservice_gradebookservices\local\service\gradebookservices';
        $this->assertTrue($service::is_user_gradable_in_course((int) $this->course->id, $userid));
    }

    /**
     * Grade sync "on completion" (gradesynccompletion) needs the learner in completion tracking: a
     * FlexAccess participant is tracked, also when enrolled through the LTI resource as well, and once.
     */
    public function test_completion_tracking_for_grade_sync_on_completion(): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->assertGreaterThan(0, enrol_service::ensure_instance((int) $course->id));
        $only = (int) $this->getDataGenerator()->create_user()->id;
        $this->assertTrue(enrol_service::admin_enrol((int) $course->id, $only));
        $tool = \enrol_lti\helper::get_lti_tool((int) $this->getDataGenerator()->create_lti_tool((object) [
            'courseid' => $course->id,
            'ltiversion' => 'LTI-1p3',
            'gradesynccompletion' => 1,
        ])->id);
        $both = (int) $this->getDataGenerator()->create_user()->id;
        $this->assertTrue(enrol_service::admin_enrol((int) $course->id, $both));
        $this->assertSame(\enrol_lti\helper::ENROLMENT_SUCCESSFUL, \enrol_lti\helper::enrol_user($tool, $both));

        $info = new \completion_info($course);
        $this->assertTrue($info->is_tracked_user($only));
        $this->assertTrue($info->is_tracked_user($both));
        $this->assertEqualsCanonicalizing([$only, $both], array_map('intval', array_keys($info->get_tracked_users())));
    }
}
