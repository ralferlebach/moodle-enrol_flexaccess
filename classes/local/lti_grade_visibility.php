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
 * LTI grades that arrived but that teachers cannot see (consumer side, enrol_flexaccess #10).
 *
 * When an external LTI tool returns a score through AGS, Moodle stores it in grade_grades for the local
 * user even if that user is not part of the gradebook population (no role listed in
 * $CFG->gradebookroles in the course). The grade then exists in the database but is missing from the
 * grader report. This class finds such grades in FlexAccess courses, so "grade received but invisible"
 * can be told apart from "AGS delivery failed" (no grade_grades row at all).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lti_grade_visibility {
    /**
     * The common FROM/WHERE part and its parameters.
     *
     * @return array [sql, params]
     */
    private static function base(): array {
        global $DB;
        $roleids = participant_role::gradebook_roleids() ?: [0];
        [$insql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'gbr');
        $params['courselevel'] = CONTEXT_COURSE;
        $sql = "FROM {grade_grades} gg
                JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'mod' AND gi.itemmodule = 'lti'
                JOIN {context} ctx ON ctx.instanceid = gi.courseid AND ctx.contextlevel = :courselevel
               WHERE gg.finalgrade IS NOT NULL
                 AND EXISTS (SELECT 1 FROM {enrol} fe WHERE fe.courseid = gi.courseid AND fe.enrol = 'flexaccess')
                 AND EXISTS (
                     SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                      WHERE ue.userid = gg.userid AND e.courseid = gi.courseid AND e.enrol = 'flexaccess'
                 )
                 AND NOT EXISTS (
                     SELECT 1 FROM {role_assignments} ra
                      WHERE ra.userid = gg.userid AND ra.contextid = ctx.id AND ra.roleid $insql
                 )";
        return [$sql, $params];
    }

    /**
     * Grades of LTI activities held by FlexAccess participants outside the gradebook population.
     *
     * @param int $limit Maximum rows; 0 = all.
     * @return \stdClass[] gradeid, userid, courseid, itemid.
     */
    public static function find_invisible(int $limit = 200): array {
        global $DB;
        [$sql, $params] = self::base();
        return array_values($DB->get_records_sql(
            "SELECT gg.id AS gradeid, gg.userid, gi.courseid, gi.id AS itemid $sql ORDER BY gi.courseid, gg.userid",
            $params,
            0,
            $limit
        ));
    }

    /**
     * Complete number of such grades.
     *
     * @return int
     */
    public static function count_invisible(): int {
        global $DB;
        [$sql, $params] = self::base();
        return (int) $DB->count_records_sql("SELECT COUNT(1) $sql", $params);
    }
}
