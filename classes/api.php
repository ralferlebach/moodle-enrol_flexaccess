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
     * Whether the course currently offers an anonymous FlexAccess entry method.
     *
     * True only when a FlexAccess enrolment method is enabled, the access window is open and at
     * least one anonymous method (temporary, quick registration or guest) is permitted. Used to
     * decide whether to advertise or serve the anonymous entry page, avoiding course enumeration.
     *
     * @param int $courseid Course id.
     * @param int|null $now Current time.
     * @return bool
     */
    public static function offers_anonymous_entry(int $courseid, ?int $now = null): bool {
        $now = $now ?? time();
        if (!self::is_target_enabled($courseid)) {
            return false;
        }
        $policy = self::get_effective_policy($courseid);
        if (!local\access_gate::is_flexaccess_open($policy, $now)) {
            return false;
        }
        return $policy->allowtemporary || $policy->allowquick || $policy->allowguest;
    }

    /**
     * Whether the course currently offers guest access through FlexAccess.
     *
     * Guest access is an anonymous entry method, so it is subject to the availability window.
     *
     * @param int $courseid Course id.
     * @param int|null $now Current time.
     * @return bool
     */
    public static function offers_guest_access(int $courseid, ?int $now = null): bool {
        $now = $now ?? time();
        if (!self::is_target_enabled($courseid)) {
            return false;
        }
        $policy = self::get_effective_policy($courseid);
        if (!local\access_gate::is_flexaccess_open($policy, $now)) {
            return false;
        }
        // Only offer the guest button when the course really has a usable core guest enrolment;
        // otherwise "enter as guest" would land on a course that refuses guest entry.
        return $policy->allowguest && self::has_usable_guest_enrolment($courseid);
    }

    /**
     * Whether the course has an enabled core guest enrolment instance and the guest plugin is on.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    private static function has_usable_guest_enrolment(int $courseid): bool {
        global $DB;
        if (!enrol_is_enabled('guest')) {
            return false;
        }
        return $DB->record_exists('enrol', [
            'enrol' => 'guest',
            'courseid' => $courseid,
            'status' => ENROL_INSTANCE_ENABLED,
        ]);
    }

    /**
     * Whether the entry page should offer a link to normal Moodle login.
     *
     * Normal login is a fallback for people who already have an account, so it is not tied to the
     * anonymous-access window.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    public static function offers_normal_login(int $courseid): bool {
        if (!self::is_target_enabled($courseid)) {
            return false;
        }
        return self::get_effective_policy($courseid)->allownormallogin;
    }

    /**
     * Whether the course offers the email-link (magic) login as a FlexAccess entry method.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    public static function offers_magic_login(int $courseid): bool {
        if (!self::is_target_enabled($courseid)) {
            return false;
        }
        return self::get_effective_policy($courseid)->allowmagiclogin;
    }

    /**
     * Whether the course currently offers quick registration.
     *
     * @param int $courseid Course id.
     * @param int|null $now Current time.
     * @return bool
     */
    public static function offers_quick_registration(int $courseid, ?int $now = null): bool {
        $now = $now ?? time();
        if (!self::is_target_enabled($courseid)) {
            return false;
        }
        $policy = self::get_effective_policy($courseid);
        if (!local\access_gate::is_flexaccess_open($policy, $now)) {
            return false;
        }
        return $policy->allowquick;
    }

    /**
     * Whether temporary access for the course is gated by a shared access key.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    public static function requires_temporary_access_key(int $courseid): bool {
        $policy = self::get_effective_policy($courseid);
        return $policy->temporaryaccesskeyscope !== 'none'
            && \enrol_flexaccess\local\access_key_service::has_configured_key($courseid, $policy);
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

    /**
     * FlexAccess enrolments of a user (recovery snapshot), optionally limited to one course.
     *
     * @param int $userid User id.
     * @param int|null $courseid Optional course id.
     * @param int|null $now Current time.
     * @return \stdClass[] ueid, enrolid, courseid, status, timestart, timeend, expiryaction, expired.
     */
    public static function get_user_enrolments(int $userid, ?int $courseid = null, ?int $now = null): array {
        return local\enrolment_admin::get_user_enrolments($userid, $courseid, $now);
    }

    /**
     * Users holding a FlexAccess enrolment in a course.
     *
     * @param int $courseid Course id.
     * @return int[]
     */
    public static function get_course_userids(int $courseid): array {
        return local\enrolment_admin::get_course_userids($courseid);
    }

    /**
     * Reactivate a suspended FlexAccess enrolment (explicit administrative decision).
     *
     * @param int $ueid User-enrolment id.
     * @param int|null $courseid When set, the enrolment must belong to this course.
     * @param int|null $now Current time.
     * @return \stdClass|null Old/new status and end time, or null when not applicable.
     */
    public static function reactivate_enrolment(int $ueid, ?int $courseid = null, ?int $now = null): ?\stdClass {
        return local\enrolment_admin::reactivate($ueid, $courseid, $now);
    }

    /**
     * Enrol a previously unenrolled user again (explicit, confirmed administrative decision).
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param bool $restrict Whether the visitor restriction applies (temporary accounts).
     * @param int|null $now Current time.
     * @return bool
     */
    public static function reenrol_user(int $courseid, int $userid, bool $restrict, ?int $now = null): bool {
        return local\enrol_service::admin_enrol($courseid, $userid, $restrict, $now);
    }

    /**
     * Map the FlexAccess enrolments of a merged-away identity onto the surviving one.
     *
     * @param int $fromuserid Source identity.
     * @param int $touserid Surviving identity.
     * @param bool $permanent Whether the merge establishes permanent course access.
     * @param int|null $now Current time.
     * @return \stdClass ->transferred, ->merged.
     */
    public static function transfer_user_enrolments(
        int $fromuserid,
        int $touserid,
        bool $permanent = false,
        ?int $now = null
    ): \stdClass {
        return local\enrolment_admin::transfer($fromuserid, $touserid, $permanent, $now);
    }

    /**
     * Role-model problems of the FlexAccess participant and restriction roles.
     *
     * @return string[] Problem codes; empty when the role model is correct.
     */
    public static function role_model_problems(): array {
        return local\readiness::role_model_problems();
    }

    /**
     * Recreate/repair the FlexAccess roles (deterministic, idempotent).
     *
     * @return void
     */
    public static function repair_role_model(): void {
        local\readiness::repair_role_model();
    }

    /**
     * FlexAccess enrolments without their course role, and participant roles assigned site-wide.
     *
     * @param int $limit Maximum rows.
     * @return array ->missingrole, ->systemparticipant.
     */
    public static function find_role_mismatches(int $limit = 200): array {
        return local\enrolment_admin::find_role_mismatches($limit);
    }

    /**
     * Course instances enabling a method that a higher-level policy or master switch neutralises.
     *
     * @param int|null $courseid Optional single course.
     * @param int $limit Maximum number of conflicts.
     * @return \stdClass[] courseid, enrolid, flag, cause.
     */
    public static function policy_conflicts(?int $courseid = null, int $limit = 200): array {
        return local\readiness::policy_conflicts($courseid, $limit);
    }

    /**
     * Compact readiness verdict of a course (empty = ready).
     *
     * @param int $courseid Course id.
     * @return string[]
     */
    public static function course_readiness_problems(int $courseid): array {
        return local\readiness::course_problems($courseid);
    }

    /**
     * FlexAccess enrolments of many users in one query, with the course-role flag.
     *
     * @param int[] $userids User ids.
     * @param int|null $now Current time.
     * @return array<int, \stdClass[]>
     */
    public static function get_enrolments_for_users(array $userids, ?int $now = null): array {
        return local\enrolment_admin::get_enrolments_for_users($userids, $now);
    }

    /**
     * Remove every assignment of the restriction role from a user (any component).
     *
     * @param int $userid User id.
     * @return int Number of assignments removed.
     */
    public static function unrestrict_completely(int $userid): int {
        return local\participant_role::unrestrict_all($userid);
    }

    /**
     * Remove system-level assignments of the course-only participant role.
     *
     * @return int[] Affected user ids.
     */
    public static function remove_system_participant_assignments(): array {
        return local\participant_role::remove_system_assignments();
    }
}
