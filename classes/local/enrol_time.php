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
 * Core-compatible enrolment times for FlexAccess.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/**
 * Single source of the start and end times FlexAccess writes into user enrolments.
 *
 * Core's active-only populations (get_enrolled_sql(), get_enrolled_join(), get_enrolled_users() with
 * $onlyactive) compare `ue.timestart < round(time(), -2)`. A start time of exactly time() therefore
 * drops a freshly enrolled user from those populations for up to a minute whenever the rounding goes
 * down. enrol_manual avoids this by flooring to the hundred and subtracting one second; every
 * FlexAccess enrolment path uses the same rule through this class.
 *
 * @package    enrol_flexaccess
 */
final class enrol_time {
    /**
     * Start time of an enrolment that is meant to begin immediately.
     *
     * Floors to the 100-second grid Core rounds to and subtracts one second, so that the strict
     * `timestart < round(now, -2)` comparison holds from the very first request on.
     *
     * @param int|null $now Current time.
     * @return int
     */
    public static function immediate_start(?int $now = null): int {
        $now = $now ?? time();
        return intdiv($now, 100) * 100 - 1;
    }

    /**
     * End time of an enrolment that begins now, from the FlexAccess enrolment period of the instance.
     *
     * The FlexAccess instance configuration is authoritative for the period; 0 means "no end".
     *
     * @param int $enrolid FlexAccess enrol instance id.
     * @param int|null $now Current time.
     * @return int
     */
    public static function end_for_instance(int $enrolid, ?int $now = null): int {
        $now = $now ?? time();
        $config = instance_config::load($enrolid);
        $period = $config ? (int) $config->enrolperiod : 0;
        return $period > 0 ? $now + $period : 0;
    }
}
