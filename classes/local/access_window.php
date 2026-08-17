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
 * Pure access-window logic for enrol_flexaccess.
 *
 * The access window ("available from"/"available until") is an independent access
 * condition, combinable with the shared access key, and strictly distinct from account
 * lifetime and enrolment lifetime.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/** Stateless helpers evaluating a time-based access window. */
final class access_window {
    /**
     * Whether the window is open at the given time.
     *
     * A bound of 0 means "unbounded" on that side. The lower bound is inclusive and the
     * upper bound is exclusive, so an instance stops offering access exactly at
     * "available until".
     *
     * @param int $from Window start; 0 = no lower bound.
     * @param int $until Window end; 0 = no upper bound.
     * @param int $now Unix timestamp to evaluate.
     * @return bool
     */
    public static function is_open(int $from, int $until, int $now): bool {
        if ($from > 0 && $now < $from) {
            return false;
        }
        if ($until > 0 && $now >= $until) {
            return false;
        }
        return true;
    }

    /**
     * Whether a configured range is valid.
     *
     * Both bounds may be 0 (unbounded). When both are set, the start must be strictly
     * before the end.
     *
     * @param int $from Window start.
     * @param int $until Window end.
     * @return bool
     */
    public static function is_valid_range(int $from, int $until): bool {
        if ($from < 0 || $until < 0) {
            return false;
        }
        if ($from > 0 && $until > 0 && $from >= $until) {
            return false;
        }
        return true;
    }
}
