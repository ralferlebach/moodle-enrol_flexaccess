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
 * Public facade for enrol_flexaccess.
 *
 * This is the stable, cross-plugin entry point consumed by auth_flexaccess, tool_flexaccess
 * and mod_flexaccess. It only reads policy and capacity; it does not create enrolments here.
 * Callers must invoke it lazily at runtime — never during install/upgrade — because the
 * suite forms an accepted auth <-> enrol dependency cycle (see ADR-010).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use enrol_flexaccess\local\policy;
use enrol_flexaccess\local\policy_assembler;
use enrol_flexaccess\local\capacity_service;

/**
 * Read-only cross-plugin facade.
 *
 * @package    enrol_flexaccess
 */
final class api {
    /**
     * Whether the course has an enabled FlexAccess enrolment instance.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    public static function is_target_enabled(int $courseid): bool {
        global $DB;
        return $DB->record_exists('enrol', [
            'enrol' => 'flexaccess',
            'courseid' => $courseid,
            'status' => ENROL_INSTANCE_ENABLED,
        ]);
    }

    /**
     * Resolve the effective FlexAccess policy for a course.
     *
     * Identity-dependent rules (role/cohort) require a known user and are applied only when a
     * user id is supplied; anonymous callers receive the identity-independent policy.
     *
     * @param int $courseid Course id.
     * @param int|null $userid Optional known user id.
     * @return policy
     */
    public static function get_effective_policy(int $courseid, ?int $userid = null): policy {
        $policy = policy_assembler::assemble($courseid);
        if ($userid !== null && !local\restriction_service::permits($courseid, $userid)) {
            // The user is restricted: withdraw FlexAccess methods; normal login is unaffected.
            $policy->allowtemporary = false;
            $policy->allowquick = false;
            $policy->allowguest = false;
        }
        return $policy;
    }

    /**
     * Whether a known user is permitted by course restrictions to use FlexAccess.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return bool
     */
    public static function is_user_permitted(int $courseid, int $userid): bool {
        return local\restriction_service::permits($courseid, $userid);
    }

    /**
     * Count active FlexAccess enrolments across the course's enabled instances.
     *
     * @param int $courseid Course id.
     * @param int|null $now Evaluation time.
     * @return int
     */
    public static function get_active_enrolment_count(int $courseid, ?int $now = null): int {
        global $DB;
        $instances = $DB->get_records('enrol', [
            'enrol' => 'flexaccess',
            'courseid' => $courseid,
            'status' => ENROL_INSTANCE_ENABLED,
        ], '', 'id');
        $total = 0;
        foreach ($instances as $instance) {
            $total += capacity_service::count_active_enrolments((int) $instance->id, $now);
        }
        return $total;
    }
}
