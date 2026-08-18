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
 * Participant capacity logic for enrol_flexaccess.
 *
 * The governing quantity is the number of *active* FlexAccess enrolments of an instance,
 * not the number of accounts ever created; expired access frees capacity. There is no
 * waitlist in the first implementation (ADR-011).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/**
 * Counts active enrolments and guards capacity with the Moodle Lock API.
 *
 * @package    enrol_flexaccess
 */
final class capacity_service {
    /**
     * Lock factory type used to serialise capacity checks.
     */
    private const LOCK_TYPE = 'enrol_flexaccess_capacity';

    /**
     * Whether a further enrolment fits within the configured maximum.
     *
     * @param int $active Current number of active enrolments.
     * @param int $max Configured maximum; 0 (or less) means unlimited.
     * @return bool
     */
    public static function has_free_capacity(int $active, int $max): bool {
        if ($max <= 0) {
            return true;
        }
        return $active < $max;
    }

    /**
     * Count active FlexAccess enrolments of an enrol instance.
     *
     * "Active" means an active user-enrolment status whose end time has not passed.
     *
     * @param int $enrolid Core enrol instance id.
     * @param int|null $now Evaluation time; defaults to the current time.
     * @return int
     */
    public static function count_active_enrolments(int $enrolid, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $sql = "SELECT COUNT(ue.id)
                  FROM {user_enrolments} ue
                 WHERE ue.enrolid = :enrolid
                   AND ue.status = :active
                   AND (ue.timeend = 0 OR ue.timeend > :now)";
        return (int) $DB->count_records_sql($sql, [
            'enrolid' => $enrolid,
            'active' => ENROL_USER_ACTIVE,
            'now' => $now,
        ]);
    }

    /**
     * Whether the instance is currently full.
     *
     * @param int $enrolid Core enrol instance id.
     * @param int $max Configured maximum; 0 = unlimited.
     * @param int|null $now Evaluation time.
     * @return bool
     */
    public static function is_full(int $enrolid, int $max, ?int $now = null): bool {
        if ($max <= 0) {
            return false;
        }
        return !self::has_free_capacity(self::count_active_enrolments($enrolid, $now), $max);
    }

    /**
     * Run a callback inside a per-instance capacity lock.
     *
     * The runtime enrolment flow uses this to make "count active + enrol" atomic and free
     * of races between concurrent requests. The callback receives no arguments and its
     * return value is passed through.
     *
     * @param int $enrolid Core enrol instance id.
     * @param callable $callback Critical section to execute while the lock is held.
     * @param int $timeout Seconds to wait for the lock.
     * @return mixed The callback result.
     * @throws \moodle_exception When the lock cannot be acquired.
     */
    public static function run_with_lock(int $enrolid, callable $callback, int $timeout = 10) {
        $factory = \core\lock\lock_config::get_lock_factory(self::LOCK_TYPE);
        $lock = $factory->get_lock('instance_' . $enrolid, $timeout);
        if (!$lock) {
            throw new \moodle_exception('error:capacitylock', 'enrol_flexaccess');
        }
        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
