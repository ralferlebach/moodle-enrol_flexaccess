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
 * Core-conformant creation and reactivation of FlexAccess enrolments (enrol_flexaccess #9).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use enrol_flexaccess\local\enrol_service;
use enrol_flexaccess\local\enrol_time;
use enrol_flexaccess\local\enrolment_admin;
use enrol_flexaccess\local\enrolment_effective_state as state;
use enrol_flexaccess\local\participant_role;

/**
 * Enrolment lifecycle tests against Core's own active-enrolment consumers.
 *
 * @package    enrol_flexaccess
 * @covers \enrol_flexaccess\local\enrol_time
 * @covers \enrol_flexaccess\local\enrol_service
 * @covers \enrol_flexaccess\local\enrolment_admin
 * @covers \enrol_flexaccess\local\enrolment_effective_state
 */
final class enrolment_lifecycle_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \enrol_flexaccess_plugin Plugin. */
    private \enrol_flexaccess_plugin $plugin;

    /**
     * Enable both halves of FlexAccess and create a course with its FlexAccess instance.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $this->course = $this->getDataGenerator()->create_course();
        $this->plugin = enrol_get_plugin('flexaccess');
    }

    /**
     * Ids of the users Core counts as actively enrolled in the course.
     *
     * @return int[]
     */
    private function core_active_userids(): array {
        $context = \context_course::instance((int) $this->course->id);
        $users = get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true);
        return array_map('intval', array_keys($users));
    }

    /**
     * Count of the active-only population through get_enrolled_sql().
     *
     * @param int $userid User to look for.
     * @return bool Whether the user is part of the active-only SQL population.
     */
    private function in_active_sql(int $userid): bool {
        global $DB;
        $context = \context_course::instance((int) $this->course->id);
        [$sql, $params] = get_enrolled_sql($context, '', 0, true);
        return $DB->record_exists_sql("SELECT 1 FROM ($sql) je WHERE je.id = :uid", $params + ['uid' => $userid]);
    }

    /**
     * The FlexAccess instance of the course (created on demand).
     *
     * @return \stdClass
     */
    private function instance(): \stdClass {
        global $DB;
        $enrolid = enrol_service::ensure_instance((int) $this->course->id);
        $this->assertGreaterThan(0, $enrolid);
        return $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
    }

    /**
     * For every position inside Core's 100-second rounding window the immediate start satisfies
     * `timestart < round(now, -2)` - including the half where round() goes down, which time() fails.
     */
    public function test_immediate_start_inside_rounding_window(): void {
        $base = 1790000000;
        for ($offset = 0; $offset < 100; $offset++) {
            $now = $base + $offset;
            $start = enrol_time::immediate_start($now);
            $this->assertLessThan(round($now, -2), $start, "offset $offset");
            $this->assertLessThanOrEqual($now, $start);
            $this->assertGreaterThan($now - 101, $start, 'The start may only be moved back minimally.');
            // A plain time() start would have dropped out whenever the rounding goes down.
            if ($offset > 0 && $offset < 50) {
                $this->assertFalse($now < round($now, -2));
            }
        }
    }

    /**
     * Right after enrol_with_capacity() the user is part of Core's active-only populations.
     */
    public function test_enrol_with_capacity_is_immediately_active_in_core(): void {
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->instance();
        $this->assertSame('enrolled', enrol_service::enrol_with_capacity((int) $instance->id, (int) $user->id));
        $this->assertContains((int) $user->id, $this->core_active_userids());
        $this->assertTrue($this->in_active_sql((int) $user->id));
        // Control: is_enrolled() (exact time, not rounded) agrees.
        $this->assertTrue(is_enrolled(\context_course::instance((int) $this->course->id), $user, '', true));
        $this->assertSame(state::OK, api::effective_enrolment_state((int) $user->id, (int) $this->course->id)->state);
    }

    /**
     * Right after admin_enrol() the user is part of Core's active-only populations.
     */
    public function test_admin_enrol_is_immediately_active_in_core(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assertTrue(enrol_service::admin_enrol((int) $this->course->id, (int) $user->id));
        $this->assertContains((int) $user->id, $this->core_active_userids());
        $this->assertTrue($this->in_active_sql((int) $user->id));
        $this->assertTrue(is_enrolled(\context_course::instance((int) $this->course->id), $user, '', true));
    }

    /**
     * A re-enrolment uses the same Core-compatible start time.
     */
    public function test_reenrolment_uses_core_compatible_start(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->instance();
        $this->plugin->enrol_user($instance, (int) $user->id, participant_role::get_id(), time() + 1000);
        $this->assertNotContains((int) $user->id, $this->core_active_userids());
        $this->assertTrue(api::reenrol_user((int) $this->course->id, (int) $user->id, false));
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertLessThan(round(time(), -2), (int) $ue->timestart);
        $this->assertContains((int) $user->id, $this->core_active_userids());
    }

    /**
     * Reactivation on a disabled instance reports no success and activates nothing.
     */
    public function test_reactivate_on_disabled_instance_fails_closed(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->instance();
        $this->plugin->enrol_user($instance, (int) $user->id, participant_role::get_id(), 0, 0, ENROL_USER_SUSPENDED);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $instance->id]);
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);

        $result = api::reactivate_enrolment((int) $ue->id);
        $this->assertSame(enrolment_admin::BLOCKED_INSTANCE_DISABLED, $result->outcome);
        $this->assertSame(ENROL_USER_SUSPENDED, (int) $DB->get_field('user_enrolments', 'status', ['id' => $ue->id]));
        $this->assertSame(ENROL_INSTANCE_DISABLED, (int) $DB->get_field('enrol', 'status', ['id' => $instance->id]));
        $this->assertSame(state::INSTANCE_DISABLED, $result->effective->state);
        $this->assertNotContains((int) $user->id, $this->core_active_userids());
    }

    /**
     * Reactivation while the plugin is disabled site-wide reports no success either.
     */
    public function test_reactivate_with_disabled_plugin_fails_closed(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->instance();
        $this->plugin->enrol_user($instance, (int) $user->id, participant_role::get_id(), 0, 0, ENROL_USER_SUSPENDED);
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);
        set_config('enrol_plugins_enabled', 'manual');
        $result = api::reactivate_enrolment((int) $ue->id);
        $this->assertSame(enrolment_admin::BLOCKED_PLUGIN_DISABLED, $result->outcome);
        $this->assertSame(ENROL_USER_SUSPENDED, (int) $DB->get_field('user_enrolments', 'status', ['id' => $ue->id]));
    }

    /**
     * A disabled existing instance is never shadowed by a second, enabled one.
     */
    public function test_reenrol_on_disabled_instance_creates_no_second_instance(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->instance();
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $instance->id]);

        $this->assertSame(0, enrol_service::ensure_instance((int) $this->course->id));
        $outcome = api::reenrol_user_outcome((int) $this->course->id, (int) $user->id, false);
        $this->assertSame(enrol_service::INSTANCE_DISABLED, $outcome->status);
        $this->assertFalse(api::reenrol_user((int) $this->course->id, (int) $user->id, false));
        $this->assertSame(1, $DB->count_records('enrol', ['enrol' => 'flexaccess', 'courseid' => $this->course->id]));
        $this->assertSame(ENROL_INSTANCE_DISABLED, (int) $DB->get_field('enrol', 'status', ['id' => $instance->id]));
        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $user->id]));
    }

    /**
     * Several instances are an inconsistency: diagnosed, never silently reused.
     */
    public function test_multiple_instances_are_diagnosed_not_reused(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $first = $this->instance();
        $second = clone $first;
        unset($second->id);
        $DB->insert_record('enrol', $second);

        $this->assertSame(
            enrol_service::INSTANCE_MULTIPLE,
            enrol_service::resolve_instance((int) $this->course->id)->status
        );
        $this->assertFalse(enrol_service::admin_enrol((int) $this->course->id, (int) $user->id));
        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $user->id]));
        $this->assertSame(1, api::count_multiple_instances());
        $found = api::find_multiple_instances();
        $this->assertSame((int) $this->course->id, (int) $found[0]->courseid);
        $this->assertSame(2, (int) $found[0]->instances);
        $this->assertSame(
            state::MULTIPLE_INSTANCES,
            api::effective_enrolment_state((int) $user->id, (int) $this->course->id)->state
        );
    }

    /**
     * Reactivation puts a missing participant role back into the course; foreign roles stay untouched.
     */
    public function test_reactivate_repairs_missing_participant_role_only(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->instance();
        $context = \context_course::instance((int) $this->course->id);
        $studentid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $this->course->id, 'student', 'manual');
        $this->plugin->enrol_user($instance, (int) $user->id, participant_role::get_id(), 0, 0, ENROL_USER_SUSPENDED);
        role_unassign(participant_role::get_id(), (int) $user->id, $context->id);
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);
        $this->assertSame(
            state::USER_ENROLMENT_SUSPENDED,
            api::effective_enrolment_state((int) $user->id, (int) $this->course->id)->state
        );

        $result = api::reactivate_enrolment((int) $ue->id);
        $this->assertSame(enrolment_admin::REACTIVATED, $result->outcome);
        $this->assertTrue($result->rolerepaired);
        $this->assertSame(state::OK, $result->effective->state);
        $this->assertTrue(user_has_role_assignment((int) $user->id, participant_role::get_id(), $context->id));
        // The manual student role is still there, exactly once.
        $this->assertSame(1, $DB->count_records('role_assignments', [
            'userid' => $user->id, 'roleid' => $studentid, 'contextid' => $context->id,
        ]));
    }

    /**
     * An already active enrolment without its role is repaired, but reported as "already active".
     */
    public function test_reactivate_active_enrolment_repairs_role(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->assertTrue(enrol_service::admin_enrol((int) $this->course->id, (int) $user->id));
        $context = \context_course::instance((int) $this->course->id);
        role_unassign(participant_role::get_id(), (int) $user->id, $context->id);
        $this->assertSame(
            state::PARTICIPANT_ROLE_MISSING,
            api::effective_enrolment_state((int) $user->id, (int) $this->course->id)->state
        );
        $ueid = (int) $DB->get_field('user_enrolments', 'id', ['userid' => $user->id]);
        $result = api::reactivate_enrolment($ueid);
        $this->assertSame(enrolment_admin::ALREADY_ACTIVE, $result->outcome);
        $this->assertTrue($result->rolerepaired);
        $this->assertSame(state::OK, $result->effective->state);
    }

    /**
     * Without the participant role definition neither reactivation nor admin_enrol reports success.
     */
    public function test_missing_role_definition_fails_closed(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $instance = $this->instance();
        $this->plugin->enrol_user($instance, (int) $user->id, participant_role::get_id(), 0, 0, ENROL_USER_SUSPENDED);
        $ueid = (int) $DB->get_field('user_enrolments', 'id', ['userid' => $user->id]);
        delete_role(participant_role::get_id());

        $result = api::reactivate_enrolment($ueid);
        $this->assertSame(enrolment_admin::BLOCKED_ROLE_MISSING, $result->outcome);
        $this->assertSame(ENROL_USER_SUSPENDED, (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid]));

        $outcome = enrol_service::admin_enrol_outcome((int) $this->course->id, (int) $other->id);
        $this->assertSame(enrol_service::ROLE_MISSING, $outcome->status);
        $this->assertFalse(enrol_service::admin_enrol((int) $this->course->id, (int) $other->id));
        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $other->id]));
    }

    /**
     * A participant role that is lost after enrolment surfaces in the effective state.
     */
    public function test_missing_course_role_is_not_ok(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assertTrue(enrol_service::admin_enrol((int) $this->course->id, (int) $user->id));
        role_unassign(participant_role::get_id(), (int) $user->id, \context_course::instance((int) $this->course->id)->id);
        $state = api::effective_enrolment_state((int) $user->id, (int) $this->course->id);
        $this->assertSame(state::PARTICIPANT_ROLE_MISSING, $state->state);
        $this->assertSame(1, api::count_missing_course_roles());
    }

    /**
     * An expired enrolment receives a valid new end time on reactivation and is effective again.
     */
    public function test_reactivate_expired_enrolment_gets_new_end(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->instance();
        $DB->set_field('enrol_flexaccess_instance', 'enrolperiod', DAYSECS, ['enrolid' => $instance->id]);
        $now = time();
        $this->plugin->enrol_user($instance, (int) $user->id, participant_role::get_id(), $now - 3 * DAYSECS, $now - DAYSECS);
        $this->assertSame(state::EXPIRED, api::effective_enrolment_state((int) $user->id, (int) $this->course->id)->state);
        $ueid = (int) $DB->get_field('user_enrolments', 'id', ['userid' => $user->id]);

        $result = api::reactivate_enrolment($ueid, null, $now);
        $this->assertSame(enrolment_admin::REACTIVATED, $result->outcome);
        $this->assertSame($now + DAYSECS, $result->newtimeend);
        $this->assertSame(state::OK, $result->effective->state);
        $this->assertContains((int) $user->id, $this->core_active_userids());
    }

    /**
     * The effective state names every condition separately.
     */
    public function test_effective_state_distinguishes_all_conditions(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $courseid = (int) $this->course->id;
        $this->assertSame(state::USER_ENROLMENT_MISSING, api::effective_enrolment_state((int) $user->id, $courseid)->state);

        $instance = $this->instance();
        $now = time();
        $this->plugin->enrol_user($instance, (int) $user->id, participant_role::get_id(), $now + HOURSECS, 0);
        $this->assertSame(state::NOT_STARTED, api::effective_enrolment_state((int) $user->id, $courseid, $now)->state);

        $DB->set_field('user_enrolments', 'timestart', 0, ['userid' => $user->id]);
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['userid' => $user->id]);
        $this->assertSame(
            state::USER_ENROLMENT_SUSPENDED,
            api::effective_enrolment_state((int) $user->id, $courseid, $now)->state
        );

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $instance->id]);
        $both = api::effective_enrolment_state((int) $user->id, $courseid, $now);
        $this->assertSame(state::INSTANCE_DISABLED, $both->state);
        $this->assertSame([state::INSTANCE_DISABLED, state::USER_ENROLMENT_SUSPENDED], $both->problems);

        set_config('enrol_plugins_enabled', 'manual');
        $this->assertSame(state::PLUGIN_DISABLED, api::effective_enrolment_state((int) $user->id, $courseid, $now)->state);
    }

    /**
     * admin_enrol() refuses while the FlexAccess enrolment plugin is disabled site-wide.
     */
    public function test_admin_enrol_with_disabled_plugin_fails_closed(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        set_config('enrol_plugins_enabled', 'manual');
        $this->assertSame(
            enrol_service::PLUGIN_DISABLED,
            enrol_service::admin_enrol_outcome((int) $this->course->id, (int) $user->id)->status
        );
        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $user->id]));
    }

    /**
     * The administrative enrolment uses the FlexAccess enrolment period of the instance configuration.
     */
    public function test_admin_enrol_uses_flexaccess_enrolment_period(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->instance();
        $DB->set_field('enrol_flexaccess_instance', 'enrolperiod', 2 * DAYSECS, ['enrolid' => $instance->id]);
        $DB->set_field('enrol', 'enrolperiod', 0, ['id' => $instance->id]);
        $now = time();
        $this->assertTrue(enrol_service::admin_enrol((int) $this->course->id, (int) $user->id, false, $now));
        $this->assertSame(
            $now + 2 * DAYSECS,
            (int) $DB->get_field('user_enrolments', 'timeend', ['userid' => $user->id])
        );
    }
}
