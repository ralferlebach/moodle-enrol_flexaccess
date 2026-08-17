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
 * Loads applicable FlexAccess restrictions and resolves user attributes.
 *
 * Identity-dependent rules are only meaningful for a known user; anonymous callers are never
 * evaluated here (the facade skips this for a null user id).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/** Resolves whether a user is permitted by course restrictions. */
final class restriction_service {
    /** Restriction table. */
    private const TABLE = 'enrol_flexaccess_restrict';

    /**
     * Whether the user is permitted to use FlexAccess in the course.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return bool
     */
    public static function permits(int $courseid, int $userid): bool {
        $restrictions = self::applicable($courseid);
        if (!$restrictions) {
            return true;
        }
        [$roleids, $cohortids] = self::user_attributes($courseid, $userid);
        return restriction_evaluator::permit($restrictions, $roleids, $cohortids);
    }

    /**
     * Load restrictions applicable to a course (system, category ancestry, course).
     *
     * @param int $courseid Course id.
     * @return array<\stdClass>
     */
    private static function applicable(int $courseid): array {
        global $DB;
        $course = get_course($courseid);
        $conditions = ["(scope = 'system')", "(scope = 'course' AND scopeid = :cid)"];
        $params = ['cid' => $courseid];

        $catids = [];
        if (!empty($course->category)) {
            $cat = \core_course_category::get($course->category, IGNORE_MISSING, true);
            if ($cat) {
                $catids = array_merge($cat->get_parents(), [$cat->id]);
            }
        }
        if ($catids) {
            [$insql, $inparams] = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'cat');
            $conditions[] = "(scope = 'category' AND scopeid $insql)";
            $params += $inparams;
        }
        return array_values($DB->get_records_select(self::TABLE, implode(' OR ', $conditions), $params));
    }

    /**
     * Resolve the user's role ids (in the course context) and cohort ids.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return array{0: array, 1: array}
     */
    private static function user_attributes(int $courseid, int $userid): array {
        global $DB;
        $context = \context_course::instance($courseid);
        $roles = get_user_roles($context, $userid, true);
        $roleids = array_values(array_unique(array_map(static fn($r) => (int) $r->roleid, $roles)));
        $cohortids = array_map('intval',
            $DB->get_fieldset_select('cohort_members', 'cohortid', 'userid = ?', [$userid]));
        return [$roleids, $cohortids];
    }
}
