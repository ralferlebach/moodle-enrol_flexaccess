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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Temporary-user access-key policy and verification boundary.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/**
 * Verifies shared access keys without exposing persisted hashes to callers.
 *
 * @package    enrol_flexaccess
 */
final class access_key_service {
    /**
     * Verify a clear-text candidate against a stored hash.
     *
     * This low-level helper exists for unit testing and internal use. Public cross-plugin APIs must resolve the
     * effective system/course hash internally and return only a boolean result.
     *
     * @param string $candidate Candidate supplied by the user.
     * @param string|null $hash Persisted password hash.
     * @return bool
     */
    public static function verify_candidate(string $candidate, ?string $hash): bool {
        if ($candidate === '' || $hash === null || $hash === '') {
            return false;
        }
        return password_verify($candidate, $hash);
    }

    /**
     * Verify a candidate against the effective access key for a course.
     *
     * Resolves the effective hash internally from the policy scope (system or course) so callers
     * never handle persisted hashes, and returns only a boolean.
     *
     * @param int $courseid Course id.
     * @param \enrol_flexaccess\local\policy $policy Effective policy for the course.
     * @param string $candidate Clear-text key supplied by the user.
     * @return bool
     */
    public static function verify(int $courseid, policy $policy, string $candidate): bool {
        return self::verify_candidate($candidate, self::resolve_hash($courseid, $policy));
    }

    /**
     * Resolve the effective access-key hash for a course, or null when no key applies.
     *
     * @param int $courseid Course id.
     * @param \enrol_flexaccess\local\policy $policy Effective policy for the course.
     * @return string|null
     */
    private static function resolve_hash(int $courseid, policy $policy): ?string {
        global $DB;
        if ($policy->temporaryaccesskeyscope === 'system') {
            $hash = get_config('enrol_flexaccess', 'temporaryaccesskeyhash');
            return ($hash !== false && $hash !== '') ? $hash : null;
        }
        if ($policy->temporaryaccesskeyscope === 'course') {
            $enrolid = $DB->get_field('enrol', 'id', [
                'enrol' => 'flexaccess',
                'courseid' => $courseid,
                'status' => ENROL_INSTANCE_ENABLED,
            ], IGNORE_MULTIPLE);
            if (!$enrolid) {
                return null;
            }
            $hash = $DB->get_field('enrol_flexaccess_instance', 'temporaryaccesskeyhash', ['enrolid' => $enrolid]);
            return ($hash !== false && $hash !== null && $hash !== '') ? $hash : null;
        }
        return null;
    }
}
