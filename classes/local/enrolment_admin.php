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

namespace enrol_flexaccess\local;

/**
 * Administrative operations on FlexAccess course enrolments.
 *
 * The owning plugin's single place for reading and changing FlexAccess enrolments on behalf of the
 * recovery workflow, the identity-merge reconciliation and the health check. Every mutation goes
 * through the enrol plugin API so events, role assignments and caches stay consistent.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrolment_admin {
    /**
     * FlexAccess enrolments of a user, optionally limited to one course.
     *
     * @param int $userid User id.
     * @param int|null $courseid Optional course id.
     * @param int|null $now Current time (for the derived 'expired' flag).
     * @return \stdClass[] Each with ueid, enrolid, courseid, status, timestart, timeend, expiryaction,
     *     expired (bool: end time passed).
     */
    public static function get_user_enrolments(int $userid, ?int $courseid = null, ?int $now = null): array {
        global $DB;
        $now = $now ?? \core\di::get(\core\clock::class)->time();
        $params = ['userid' => $userid];
        $where = '';
        if ($courseid !== null) {
            $where = ' AND e.courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        $rows = $DB->get_records_sql(
            "SELECT ue.id AS ueid, ue.enrolid, e.courseid, ue.status, ue.timestart, ue.timeend
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'flexaccess'
              WHERE ue.userid = :userid $where
           ORDER BY e.courseid ASC, ue.id ASC",
            $params
        );
        $out = [];
        foreach ($rows as $row) {
            $config = instance_config::load((int) $row->enrolid);
            $out[] = (object) [
                'ueid' => (int) $row->ueid,
                'enrolid' => (int) $row->enrolid,
                'courseid' => (int) $row->courseid,
                'status' => (int) $row->status,
                'timestart' => (int) $row->timestart,
                'timeend' => (int) $row->timeend,
                'expiryaction' => ($config && $config->expiryaction === 'unenrol') ? 'unenrol' : 'suspend',
                // Ended under either Core semantics (exact access check or the rounded enrolled-users lists).
                'expired' => in_array(enrolment_effective_state::EXPIRED, [
                    enrolment_effective_state::time_window($row, $now, false),
                    enrolment_effective_state::time_window($row, $now, true),
                ], true),
            ];
        }
        return $out;
    }

    /**
     * FlexAccess enrolments of many users in one query, with role flag and effective state (no N+1).
     *
     * @param int[] $userids User ids.
     * @param int|null $now Current time.
     * @return array<int, \stdClass[]> userid => enrolments (ueid, enrolid, courseid, status, timestart,
     *     timeend, expired, hasrole, instanceenabled, instancecount, effective (state), problems).
     */
    public static function get_enrolments_for_users(array $userids, ?int $now = null): array {
        global $DB;
        $now = $now ?? \core\di::get(\core\clock::class)->time();
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        $out = array_fill_keys($userids, []);
        if (!$userids) {
            return $out;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['roleid'] = participant_role::get_id();
        $params['courselevel'] = CONTEXT_COURSE;
        $rows = $DB->get_recordset_sql(
            "SELECT ue.id AS ueid, ue.userid, ue.enrolid, e.courseid, ue.status, ue.timestart, ue.timeend,
                    e.status AS instancestatus,
                    (SELECT COUNT(1) FROM {enrol} e2 WHERE e2.courseid = e.courseid AND e2.enrol = 'flexaccess')
                        AS instancecount,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM {role_assignments} ra
                         WHERE ra.userid = ue.userid AND ra.contextid = ctx.id AND ra.roleid = :roleid
                    ) THEN 1 ELSE 0 END AS hasrole
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'flexaccess'
               JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :courselevel
              WHERE ue.userid $insql
           ORDER BY ue.userid, e.courseid, ue.id",
            $params
        );
        $pluginenabled = enrol_is_enabled('flexaccess');
        foreach ($rows as $row) {
            $problems = enrolment_effective_state::classify(
                $pluginenabled,
                (int) $row->instancecount,
                (int) $row->instancestatus === ENROL_INSTANCE_ENABLED,
                $row,
                (int) $row->hasrole === 1,
                $now
            );
            $out[(int) $row->userid][] = (object) [
                'ueid' => (int) $row->ueid,
                'enrolid' => (int) $row->enrolid,
                'courseid' => (int) $row->courseid,
                'status' => (int) $row->status,
                'timestart' => (int) $row->timestart,
                'timeend' => (int) $row->timeend,
                'expired' => in_array(enrolment_effective_state::EXPIRED, $problems, true),
                'hasrole' => (int) $row->hasrole === 1,
                'instanceenabled' => (int) $row->instancestatus === ENROL_INSTANCE_ENABLED,
                'instancecount' => (int) $row->instancecount,
                'effective' => $problems ? $problems[0] : enrolment_effective_state::OK,
                'problems' => $problems,
            ];
        }
        $rows->close();
        return $out;
    }

    /**
     * Users holding a FlexAccess enrolment in a course.
     *
     * @param int $courseid Course id.
     * @return int[] User ids.
     */
    public static function get_course_userids(int $courseid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT DISTINCT ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'flexaccess'
              WHERE e.courseid = :courseid",
            ['courseid' => $courseid]
        ));
    }

    /** Reactivation outcome: the enrolment is active again and the participant role is held. */
    public const REACTIVATED = 'reactivated';
    /** Reactivation outcome: the enrolment was already active (the role was repaired when missing). */
    public const ALREADY_ACTIVE = 'alreadyactive';
    /** Reactivation outcome: no such FlexAccess enrolment (in the given course). */
    public const NOT_FOUND = 'notfound';
    /** Reactivation outcome: refused, the FlexAccess enrolment plugin is disabled. */
    public const BLOCKED_PLUGIN_DISABLED = 'plugindisabled';
    /** Reactivation outcome: refused, the FlexAccess enrolment instance is disabled. */
    public const BLOCKED_INSTANCE_DISABLED = 'instancedisabled';
    /** Reactivation outcome: refused, the FlexAccess participant role does not exist. */
    public const BLOCKED_ROLE_MISSING = 'rolemissing';

    /**
     * Reactivate a suspended or expired FlexAccess enrolment, optionally only within one course.
     *
     * Core counts an enrolment as active only when the plugin and the instance are enabled as well, and
     * FlexAccess course capabilities come from the participant role. Reactivation therefore fails
     * closed - without writing anything - when the plugin or the instance is disabled (a deliberate
     * decision that is never reversed implicitly) or the participant role does not exist, and it puts
     * a missing participant role back into the course context. Foreign and manual roles are untouched.
     *
     * If the end time has already passed, the enrolment receives a fresh end derived from the instance's
     * enrolment period (or none), because otherwise the expiry task would suspend it again at once.
     *
     * @param int $ueid User-enrolment id.
     * @param int|null $courseid When set, the enrolment must belong to this course.
     * @param int|null $now Current time.
     * @return \stdClass ->outcome (reactivated|alreadyactive|notfound|plugindisabled|instancedisabled|
     *     rolemissing), ->ueid, ->userid, ->courseid, ->oldstatus, ->newstatus, ->oldtimeend, ->newtimeend,
     *     ->rolerepaired, ->effective (the effective state afterwards, see {@see enrolment_effective_state}).
     */
    public static function reactivate(int $ueid, ?int $courseid = null, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? \core\di::get(\core\clock::class)->time();
        $result = (object) [
            'outcome' => self::NOT_FOUND,
            'ueid' => $ueid,
            'userid' => 0,
            'courseid' => (int) $courseid,
            'oldstatus' => null,
            'newstatus' => null,
            'oldtimeend' => null,
            'newtimeend' => null,
            'rolerepaired' => false,
            'effective' => null,
        ];
        $row = $DB->get_record_sql(
            "SELECT ue.*, e.courseid, e.status AS instancestatus
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'flexaccess'
              WHERE ue.id = :id",
            ['id' => $ueid]
        );
        if (!$row || ($courseid !== null && (int) $row->courseid !== $courseid)) {
            return $result;
        }
        $result->userid = (int) $row->userid;
        $result->courseid = (int) $row->courseid;
        $result->oldstatus = (int) $row->status;
        $result->newstatus = (int) $row->status;
        $result->oldtimeend = (int) $row->timeend;
        $result->newtimeend = (int) $row->timeend;

        $roleid = participant_role::get_id();
        if (!enrol_is_enabled('flexaccess')) {
            $result->outcome = self::BLOCKED_PLUGIN_DISABLED;
        } else if ((int) $row->instancestatus !== ENROL_INSTANCE_ENABLED) {
            $result->outcome = self::BLOCKED_INSTANCE_DISABLED;
        } else if ($roleid === 0) {
            $result->outcome = self::BLOCKED_ROLE_MISSING;
        }
        if ($result->outcome !== self::NOT_FOUND) {
            $result->effective = enrolment_effective_state::evaluate($result->userid, $result->courseid, $now);
            return $result;
        }

        // Ended under either Core semantics (exact access check or the rounded enrolled-users queries,
        // enrol_flexaccess #11): an end inside the rounding window would leave the user out of Core's lists.
        $ended = in_array(enrolment_effective_state::EXPIRED, [
            enrolment_effective_state::time_window($row, $now, false),
            enrolment_effective_state::time_window($row, $now, true),
        ], true);
        $active = (int) $row->status === ENROL_USER_ACTIVE && !$ended;
        $transaction = $DB->start_delegated_transaction();
        if (!$active) {
            $instance = $DB->get_record('enrol', ['id' => $row->enrolid], '*', MUST_EXIST);
            $timeend = (int) $row->timeend;
            if ($ended) {
                $timeend = enrol_time::end_for_instance((int) $instance->id, $now);
            }
            enrol_get_plugin('flexaccess')->update_user_enrol($instance, $result->userid, ENROL_USER_ACTIVE, null, $timeend);
            $result->newstatus = ENROL_USER_ACTIVE;
            $result->newtimeend = $timeend;
        }
        if (!enrolment_effective_state::has_participant_role($result->userid, $result->courseid)) {
            // Assigned exactly as enrol_user() assigns it (the plugin's roles are not protected).
            role_assign($roleid, $result->userid, \context_course::instance($result->courseid)->id);
            $result->rolerepaired = true;
        }
        $transaction->allow_commit();
        $result->outcome = $active ? self::ALREADY_ACTIVE : self::REACTIVATED;
        $result->effective = enrolment_effective_state::evaluate($result->userid, $result->courseid, $now);
        return $result;
    }

    /**
     * Map every FlexAccess enrolment of one identity onto another (identity merge).
     *
     * Where the target has no enrolment in an instance, it is enrolled with the source's status and
     * times under the participant role. Where it already has one, the two are merged: active wins,
     * the later end wins and "no end" wins. With $permanent the transferred enrolments lose their
     * FlexAccess end time. The source is unenrolled afterwards. Repeating the call is a no-op.
     *
     * @param int $fromuserid Source identity.
     * @param int $touserid Surviving identity.
     * @param bool $permanent Whether course access becomes permanent.
     * @param int|null $now Current time.
     * @return \stdClass ->transferred, ->merged.
     */
    public static function transfer(int $fromuserid, int $touserid, bool $permanent = false, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? \core\di::get(\core\clock::class)->time();
        $plugin = enrol_get_plugin('flexaccess');
        $result = (object) ['transferred' => 0, 'merged' => 0];
        if (!$plugin || $fromuserid === $touserid) {
            return $result;
        }
        $roleid = participant_role::get_id() ?: null;
        $sources = $DB->get_records_sql(
            "SELECT ue.*
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'flexaccess'
              WHERE ue.userid = :userid",
            ['userid' => $fromuserid]
        );
        foreach ($sources as $source) {
            $instance = $DB->get_record('enrol', ['id' => $source->enrolid], '*', MUST_EXIST);
            $existing = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $touserid]);
            $timeend = $permanent ? 0 : (int) $source->timeend;
            // Permanent course access must not be cut by the temporary FlexAccess limit - neither by
            // its end time nor by the suspension that limit may already have caused.
            $status = $permanent ? ENROL_USER_ACTIVE : (int) $source->status;
            if (!$existing) {
                $plugin->enrol_user(
                    $instance,
                    $touserid,
                    $roleid,
                    (int) $source->timestart,
                    $timeend,
                    $status
                );
                $result->transferred++;
            } else {
                self::merge_into($instance, $existing, (object) [
                    'timestart' => (int) $source->timestart,
                    'timeend' => $timeend,
                    'status' => $status,
                ]);
                $result->merged++;
            }
            $plugin->unenrol_user($instance, $fromuserid);
        }
        return $result;
    }

    /**
     * Merge the values of a merged-away identity's enrolment into the survivor's enrolment of the same instance.
     *
     * Both belong to the same person, so the survivor keeps the union of the two entitlements: active when
     * either was active, the earlier start, the later end ("no end" wins only when one of them had none) - never
     * more than either had. Used by {@see self::transfer()} and, for enrolments a generic merge tool already
     * removed from the source, with the values recorded before that merge.
     *
     * @param \stdClass $instance FlexAccess enrol instance.
     * @param \stdClass $existing The survivor's user_enrolments row.
     * @param \stdClass $source timestart, timeend, status of the source's enrolment.
     * @return void
     */
    public static function merge_into(\stdClass $instance, \stdClass $existing, \stdClass $source): void {
        $plugin = enrol_get_plugin('flexaccess');
        $ends = [(int) $existing->timeend, (int) $source->timeend];
        $mergedend = in_array(0, $ends, true) ? 0 : max($ends);
        $mergedstatus = ((int) $existing->status === ENROL_USER_ACTIVE || (int) $source->status === ENROL_USER_ACTIVE)
            ? ENROL_USER_ACTIVE : ENROL_USER_SUSPENDED;
        // Calling enrol_user() on an existing enrolment updates it in place and (idempotently) makes
        // sure the FlexAccess course role is held exactly once by the surviving identity.
        $plugin->enrol_user(
            $instance,
            (int) $existing->userid,
            participant_role::get_id() ?: null,
            min((int) $existing->timestart, (int) $source->timestart),
            $mergedend,
            $mergedstatus
        );
    }

    /**
     * Apply the values of a source enrolment recorded before a generic merge removed it (auth_flexaccess #13).
     *
     * @param int $touserid Survivor.
     * @param int $enrolid FlexAccess instance.
     * @param \stdClass $source Recorded timestart, timeend, status.
     * @return bool Whether the survivor's enrolment was merged.
     */
    public static function merge_recorded(int $touserid, int $enrolid, \stdClass $source): bool {
        global $DB;
        $instance = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'flexaccess']);
        $existing = $instance ? $DB->get_record('user_enrolments', ['enrolid' => $enrolid, 'userid' => $touserid]) : null;
        if (!$instance || !$existing) {
            return false;
        }
        self::merge_into($instance, $existing, $source);
        return true;
    }

    /**
     * FlexAccess enrolments lacking their FlexAccess course role, and misplaced participant roles.
     *
     * @param int $limit Maximum rows per finding.
     * @return array ->missingrole (ue rows: userid, courseid, enrolid), ->systemparticipant (int count).
     */
    public static function find_role_mismatches(int $limit = 200): array {
        global $DB;
        $roleid = participant_role::get_id();
        $missing = [];
        if ($roleid > 0) {
            $missing = array_values($DB->get_records_sql(
                "SELECT ue.id, ue.userid, e.courseid, e.id AS enrolid
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'flexaccess'
                   JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :courselevel
              LEFT JOIN {role_assignments} ra ON ra.userid = ue.userid AND ra.contextid = ctx.id
                                              AND ra.roleid = :roleid
                  WHERE ra.id IS NULL",
                // Roles of this plugin are not protected, so enrol_user() assigns them without a
                // component; the participant role in the course context is what must be present.
                ['courselevel' => CONTEXT_COURSE, 'roleid' => $roleid],
                0,
                $limit
            ));
        }
        $system = $roleid > 0 ? $DB->count_records('role_assignments', [
            'roleid' => $roleid,
            'contextid' => \context_system::instance()->id,
        ]) : 0;
        return ['missingrole' => $missing, 'systemparticipant' => (int) $system];
    }

    /**
     * Complete number of FlexAccess enrolments whose user lacks the participant role in the course.
     *
     * @return int
     */
    public static function count_missing_course_roles(): int {
        global $DB;
        $roleid = participant_role::get_id();
        if ($roleid <= 0) {
            return 0;
        }
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'flexaccess'
               JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :courselevel
          LEFT JOIN {role_assignments} ra ON ra.userid = ue.userid AND ra.contextid = ctx.id AND ra.roleid = :roleid
              WHERE ra.id IS NULL",
            ['courselevel' => CONTEXT_COURSE, 'roleid' => $roleid]
        );
    }
}
