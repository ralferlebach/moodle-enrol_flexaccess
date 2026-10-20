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
 * Effective state of a FlexAccess course enrolment, evaluated the way Moodle core evaluates it.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/**
 * Read-only, central evaluation of whether a FlexAccess enrolment really gives course access.
 *
 * Core treats a user enrolment as active only when the enrolment plugin is enabled, the instance is
 * enabled, the user enrolment is active and the current time lies inside its time window. On top of
 * that, FlexAccess course capabilities come from the participant role in the course context. All these
 * conditions are evaluated here together, so that no caller has to recombine individual fields (and get
 * it subtly wrong). Nothing is written.
 *
 * Core uses two time semantics, and both are evaluated (enrol_flexaccess #11):
 *  - access: is_enrolled(..., onlyactive) / require_login() compare the exact time,
 *    timestart <= now < timeend (timeend 0 = open-ended);
 *  - population: get_enrolled_sql()/get_enrolled_join() with onlyactive - participants page, gradebook,
 *    completion, LTI grade service - round the time to 100 seconds (R = round(now, -2)) and compare
 *    strictly, timestart < R < timeend.
 * The window can differ by up to a minute and a half between the two. The overall state is "ok" only
 * when BOTH accept the enrolment, so no caller reports access that Core does not grant everywhere;
 * ->access and ->population keep the two results apart.
 *
 * @package    enrol_flexaccess
 */
final class enrolment_effective_state {
    /** Everything Core and FlexAccess need is in place. */
    public const OK = 'ok';
    /** The FlexAccess enrolment plugin is disabled site-wide. */
    public const PLUGIN_DISABLED = 'plugin_disabled';
    /** The course has more than one FlexAccess enrolment instance (invariant violated). */
    public const MULTIPLE_INSTANCES = 'multiple_instances';
    /** The user has no FlexAccess enrolment in the course (or the course has no instance). */
    public const USER_ENROLMENT_MISSING = 'user_enrolment_missing';
    /** The FlexAccess enrolment instance of the course is disabled. */
    public const INSTANCE_DISABLED = 'instance_disabled';
    /** The user enrolment is suspended. */
    public const USER_ENROLMENT_SUSPENDED = 'user_enrolment_suspended';
    /** The user enrolment starts in the future. */
    public const NOT_STARTED = 'not_started';
    /** The user enrolment has ended. */
    public const EXPIRED = 'expired';
    /** The FlexAccess participant role is missing in the course context. */
    public const PARTICIPANT_ROLE_MISSING = 'participant_role_missing';

    /** Evaluation order: the first applicable problem is the overall state. */
    public const PRIORITY = [
        self::PLUGIN_DISABLED,
        self::MULTIPLE_INSTANCES,
        self::USER_ENROLMENT_MISSING,
        self::INSTANCE_DISABLED,
        self::USER_ENROLMENT_SUSPENDED,
        self::NOT_STARTED,
        self::EXPIRED,
        self::PARTICIPANT_ROLE_MISSING,
    ];

    /**
     * Evaluate the effective FlexAccess enrolment of a user in a course.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @param int|null $now Evaluation time.
     * @return \stdClass ->state (one of the constants), ->problems (string[] all applicable problems in
     *     priority order; empty when ok), ->access / ->population (the time window under each Core
     *     semantics: '' when inside, otherwise NOT_STARTED or EXPIRED), ->corenow (round(now, -2)),
     *     ->instancecount, ->enrolid (0 if none), ->instanceenabled (bool|null), ->ueid (0 if none),
     *     ->uestatus (int|null), ->timestart, ->timeend, ->hasrole, ->pluginenabled.
     */
    public static function evaluate(int $userid, int $courseid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $result = (object) [
            'state' => self::OK,
            'problems' => [],
            'userid' => $userid,
            'courseid' => $courseid,
            'pluginenabled' => enrol_is_enabled('flexaccess'),
            'instancecount' => 0,
            'enrolid' => 0,
            'instanceenabled' => null,
            'ueid' => 0,
            'uestatus' => null,
            'timestart' => 0,
            'timeend' => 0,
            'hasrole' => false,
            'access' => '',
            'population' => '',
            'corenow' => self::core_now($now),
        ];
        $instances = $DB->get_records('enrol', ['enrol' => 'flexaccess', 'courseid' => $courseid], 'id ASC', 'id, status');
        $result->instancecount = count($instances);

        $ue = null;
        if ($instances) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($instances), SQL_PARAMS_NAMED);
            $params['userid'] = $userid;
            $rows = $DB->get_records_select('user_enrolments', "userid = :userid AND enrolid $insql", $params, 'id ASC');
            // With several instances (an inconsistency in itself) prefer the enrolment on an enabled one.
            foreach ($rows as $row) {
                if ($ue === null || (int) $instances[$row->enrolid]->status === ENROL_INSTANCE_ENABLED) {
                    $ue = $row;
                    if ((int) $instances[$row->enrolid]->status === ENROL_INSTANCE_ENABLED) {
                        break;
                    }
                }
            }
        }
        $instance = $ue !== null ? $instances[$ue->enrolid] : ($instances ? reset($instances) : null);
        if ($instance) {
            $result->enrolid = (int) $instance->id;
            $result->instanceenabled = (int) $instance->status === ENROL_INSTANCE_ENABLED;
        }
        if ($ue !== null) {
            $result->ueid = (int) $ue->id;
            $result->uestatus = (int) $ue->status;
            $result->timestart = (int) $ue->timestart;
            $result->timeend = (int) $ue->timeend;
        }
        $result->hasrole = self::has_participant_role($userid, $courseid);
        $result->problems = self::classify(
            $result->pluginenabled,
            $result->instancecount,
            $result->instanceenabled,
            $ue,
            $result->hasrole,
            $now
        );
        $result->state = $result->problems ? $result->problems[0] : self::OK;
        if ($ue !== null) {
            $result->access = self::time_window($ue, $now, false);
            $result->population = self::time_window($ue, $now, true);
        }
        return $result;
    }

    /**
     * The single rule that turns the facts of one enrolment into its problems (no database access).
     *
     * Shared by {@see self::evaluate()} and by bulk readers such as enrolment_admin::get_enrolments_for_users(),
     * so that list views and single evaluations can never disagree.
     *
     * @param bool $pluginenabled Whether the FlexAccess enrolment plugin is enabled.
     * @param int $instancecount Number of FlexAccess instances in the course.
     * @param bool|null $instanceenabled Whether the enrolment's instance is enabled (null: no instance).
     * @param \stdClass|null $ue User enrolment (status, timestart, timeend), or null when missing.
     * @param bool $hasrole Whether the participant role is held in the course context.
     * @param int $now Evaluation time.
     * @return string[] Problems in priority order; empty means {@see self::OK}.
     */
    public static function classify(
        bool $pluginenabled,
        int $instancecount,
        ?bool $instanceenabled,
        ?\stdClass $ue,
        bool $hasrole,
        int $now
    ): array {
        $problems = [];
        if (!$pluginenabled) {
            $problems[] = self::PLUGIN_DISABLED;
        }
        if ($instancecount > 1) {
            $problems[] = self::MULTIPLE_INSTANCES;
        }
        if ($ue === null) {
            $problems[] = self::USER_ENROLMENT_MISSING;
        } else {
            if ($instanceenabled !== true) {
                $problems[] = self::INSTANCE_DISABLED;
            }
            if ((int) $ue->status !== ENROL_USER_ACTIVE) {
                $problems[] = self::USER_ENROLMENT_SUSPENDED;
            }
            // Both Core semantics must accept the window (see the class comment).
            foreach ([self::time_window($ue, $now, false), self::time_window($ue, $now, true)] as $window) {
                if ($window !== '') {
                    $problems[] = $window;
                }
            }
            if (!$hasrole) {
                $problems[] = self::PARTICIPANT_ROLE_MISSING;
            }
        }
        return array_values(array_filter(
            self::PRIORITY,
            static fn(string $code): bool => in_array($code, $problems, true)
        ));
    }

    /**
     * The time Core's enrolled-users queries compare against: rounded to 100 seconds.
     *
     * @param int $now Exact time.
     * @return int round($now, -2), as in get_enrolled_join().
     */
    public static function core_now(int $now): int {
        return (int) round($now, -2);
    }

    /**
     * Whether a user enrolment's time window is open under one of Core's two semantics.
     *
     * @param \stdClass $ue User enrolment (timestart, timeend).
     * @param int $now Exact time.
     * @param bool $population true: get_enrolled_sql() semantics (rounded, strict: timestart < R < timeend);
     *     false: is_enrolled() semantics (exact: timestart <= now < timeend).
     * @return string '' when open, otherwise NOT_STARTED or EXPIRED.
     */
    public static function time_window(\stdClass $ue, int $now, bool $population): string {
        $start = (int) $ue->timestart;
        $end = (int) $ue->timeend;
        if ($population) {
            $core = self::core_now($now);
            if ($start >= $core) {
                return self::NOT_STARTED;
            }
            return $end > 0 && $end <= $core ? self::EXPIRED : '';
        }
        if ($start > $now) {
            return self::NOT_STARTED;
        }
        return $end > 0 && $end <= $now ? self::EXPIRED : '';
    }

    /**
     * Whether the user holds the FlexAccess participant role in the course context (any component).
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @return bool
     */
    public static function has_participant_role(int $userid, int $courseid): bool {
        global $DB;
        $roleid = participant_role::get_id();
        if ($roleid === 0) {
            return false;
        }
        return $DB->record_exists('role_assignments', [
            'roleid' => $roleid,
            'userid' => $userid,
            'contextid' => \context_course::instance($courseid)->id,
        ]);
    }

    /**
     * Courses with more than one FlexAccess enrolment instance.
     *
     * @param int $limit Maximum rows; 0 = all.
     * @return \stdClass[] Each with courseid and instances (count).
     */
    public static function find_multiple_instances(int $limit = 200): array {
        global $DB;
        return array_values($DB->get_records_sql(
            "SELECT courseid, COUNT(1) AS instances
               FROM {enrol}
              WHERE enrol = 'flexaccess'
           GROUP BY courseid
             HAVING COUNT(1) > 1
           ORDER BY courseid ASC",
            [],
            0,
            $limit
        ));
    }

    /**
     * Complete number of courses with more than one FlexAccess enrolment instance.
     *
     * @return int
     */
    public static function count_multiple_instances(): int {
        global $DB;
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM (SELECT courseid
                       FROM {enrol}
                      WHERE enrol = 'flexaccess'
                   GROUP BY courseid
                     HAVING COUNT(1) > 1) multi"
        );
    }
}
