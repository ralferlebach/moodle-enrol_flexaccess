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
 * Pure evaluation of identity-dependent FlexAccess access restrictions.
 *
 * Restrictions gate whether a specific user may use FlexAccess in a course. Semantics:
 * a matching "deny" always blocks (deny wins); if any "allow" restrictions are present, the
 * user must match at least one of them (allowlist mode); with no "allow" restrictions and no
 * matching "deny", access is permitted.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/** Stateless restriction evaluation. */
final class restriction_evaluator {
    /**
     * Whether the user is permitted given the applicable restrictions and their attributes.
     *
     * @param array $restrictions Rows with ->kind ('role'|'cohort'), ->refid (int), ->effect ('allow'|'deny').
     * @param array $roleids Role ids the user holds in the relevant context.
     * @param array $cohortids Cohort ids the user is a member of.
     * @return bool
     */
    public static function permit(array $restrictions, array $roleids, array $cohortids): bool {
        $roleset = array_flip(array_map('intval', $roleids));
        $cohortset = array_flip(array_map('intval', $cohortids));

        $hasallow = false;
        $matchedallow = false;
        foreach ($restrictions as $restriction) {
            $matched = self::matches($restriction, $roleset, $cohortset);
            if ($restriction->effect === 'deny' && $matched) {
                return false;
            }
            if ($restriction->effect === 'allow') {
                $hasallow = true;
                $matchedallow = $matchedallow || $matched;
            }
        }
        return $hasallow ? $matchedallow : true;
    }

    /**
     * Whether a single restriction matches the user's attributes.
     *
     * @param \stdClass $restriction Restriction row.
     * @param array $roleset Role ids as a lookup set.
     * @param array $cohortset Cohort ids as a lookup set.
     * @return bool
     */
    private static function matches(\stdClass $restriction, array $roleset, array $cohortset): bool {
        if ($restriction->kind === 'role') {
            return isset($roleset[(int) $restriction->refid]);
        }
        if ($restriction->kind === 'cohort') {
            return isset($cohortset[(int) $restriction->refid]);
        }
        return false;
    }
}
