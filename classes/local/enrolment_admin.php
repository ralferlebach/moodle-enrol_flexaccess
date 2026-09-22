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
        $now = $now ?? time();
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
                'expired' => (int) $row->timeend > 0 && (int) $row->timeend <= $now,
            ];
        }
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

    /**
     * Reactivate a suspended FlexAccess enrolment, optionally only within one course.
     *
     * If its end time has already passed, it receives a fresh end derived from the instance's
     * enrolment period (or none), because otherwise the expiry task would suspend it again at once.
     *
     * @param int $ueid User-enrolment id.
     * @param int|null $courseid When set, the enrolment must belong to this course.
     * @param int|null $now Current time.
     * @return \stdClass|null Old and new status/timeend, or null when not applicable.
     */
    public static function reactivate(int $ueid, ?int $courseid = null, ?int $now = null): ?\stdClass {
        global $DB;
        $now = $now ?? time();
        $row = $DB->get_record_sql(
            "SELECT ue.*, e.courseid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'flexaccess'
              WHERE ue.id = :id",
            ['id' => $ueid]
        );
        if (!$row || ($courseid !== null && (int) $row->courseid !== $courseid)) {
            return null;
        }
        if ((int) $row->status === ENROL_USER_ACTIVE && ((int) $row->timeend === 0 || (int) $row->timeend > $now)) {
            return null;
        }
        $instance = $DB->get_record('enrol', ['id' => $row->enrolid], '*', MUST_EXIST);
        $timeend = (int) $row->timeend;
        if ($timeend > 0 && $timeend <= $now) {
            $config = instance_config::load((int) $instance->id);
            $period = $config ? (int) $config->enrolperiod : 0;
            $timeend = $period > 0 ? $now + $period : 0;
        }
        enrol_get_plugin('flexaccess')->update_user_enrol($instance, (int) $row->userid, ENROL_USER_ACTIVE, null, $timeend);
        return (object) [
            'ueid' => $ueid,
            'courseid' => (int) $row->courseid,
            'oldstatus' => (int) $row->status,
            'newstatus' => ENROL_USER_ACTIVE,
            'oldtimeend' => (int) $row->timeend,
            'newtimeend' => $timeend,
        ];
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
        $now = $now ?? time();
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
                $ends = [(int) $existing->timeend, $timeend];
                $mergedend = in_array(0, $ends, true) ? 0 : max($ends);
                $mergedstatus = ((int) $existing->status === ENROL_USER_ACTIVE || $status === ENROL_USER_ACTIVE)
                    ? ENROL_USER_ACTIVE : ENROL_USER_SUSPENDED;
                // Calling enrol_user() on an existing enrolment updates it in place and (idempotently) makes
                // sure the FlexAccess course role is held exactly once by the surviving identity.
                $plugin->enrol_user(
                    $instance,
                    $touserid,
                    $roleid,
                    min((int) $existing->timestart, (int) $source->timestart),
                    $mergedend,
                    $mergedstatus
                );
                $result->merged++;
            }
            $plugin->unenrol_user($instance, $fromuserid);
        }
        return $result;
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
}
