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
 * Capacity-guarded runtime enrolment for FlexAccess.
 *
 * Performs the "count active + enrol" step atomically inside the per-instance capacity lock so
 * that concurrent requests cannot exceed the configured maximum. The enrolment end time is
 * derived from the instance enrolment period; the account lifetime and access window are owned
 * elsewhere and are not applied here.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/**
 * Enrols users under the capacity limit.
 *
 * @package    enrol_flexaccess
 */
final class enrol_service {
    /**
     * Enrol a user into a FlexAccess instance if capacity allows.
     *
     * Thin wrapper over {@see self::reserve_and_enrol()} for the case where the account already
     * exists; the callback simply yields the given user id.
     *
     * @param int $enrolid FlexAccess enrol instance id.
     * @param int $userid User id to enrol.
     * @param int|null $now Current time.
     * @return string One of: enrolled, full, notenabled.
     */
    public static function enrol_with_capacity(int $enrolid, int $userid, ?int $now = null): string {
        return self::reserve_and_enrol($enrolid, static fn(): int => $userid, $now)->status;
    }

    /**
     * Ensure a single enabled FlexAccess enrol instance exists in a course, returning its id.
     *
     * @param int $courseid Course id.
     * @return int Enrol instance id (0 if the instance could not be created).
     */
    public static function ensure_instance(int $courseid): int {
        global $DB;
        $existing = $DB->get_record(
            'enrol',
            ['enrol' => 'flexaccess', 'courseid' => $courseid, 'status' => ENROL_INSTANCE_ENABLED],
            'id',
            IGNORE_MULTIPLE
        );
        if ($existing) {
            return (int) $existing->id;
        }
        $plugin = enrol_get_plugin('flexaccess');
        if (!$plugin) {
            return 0;
        }
        $course = get_course($courseid);
        $instanceid = $plugin->add_instance($course);
        return (int) $instanceid;
    }

    /**
     * Administratively enrol a user into a course through the FlexAccess enrol instance, so the
     * participant role, expiry and restrictions apply. Bypasses the public capacity gate: this is
     * an administrator action, not a self-service registration.
     *
     * @param int $courseid Target course id.
     * @param int $userid User to enrol.
     * @param bool $restrict Whether to apply the temporary-visitor restriction role.
     * @param int|null $now Current time.
     * @return bool Whether the enrolment succeeded.
     */
    public static function admin_enrol(int $courseid, int $userid, bool $restrict = false, ?int $now = null): bool {
        global $DB;
        $now = $now ?? time();
        $enrolid = self::ensure_instance($courseid);
        if ($enrolid === 0) {
            return false;
        }
        $instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        $plugin = enrol_get_plugin('flexaccess');
        $roleid = participant_role::get_id();
        $timeend = (int) $instance->enrolperiod > 0 ? $now + (int) $instance->enrolperiod : 0;
        $plugin->enrol_user($instance, $userid, $roleid, $now, $timeend, ENROL_USER_ACTIVE);
        if ($restrict) {
            participant_role::restrict($userid);
        }
        // Keep the participant-list override in step with the current effective policy on every
        // entry, so a later change to a higher-level default reaches the course without a re-save.
        participant_list_access::sync(
            (int) $instance->courseid,
            \enrol_flexaccess\api::get_effective_policy((int) $instance->courseid)->participantlistaccess
        );
        return true;
    }

    /**
     * Reserve a capacity slot under the lock, then create and enrol the account inside the same
     * critical section.
     *
     * The account-creating callback is invoked only after the authoritative capacity re-check has
     * secured a slot, so a request that loses the capacity race never leaves an orphaned account
     * behind. The callback runs while the per-instance lock is held and must return the new user id.
     *
     * @param int $enrolid FlexAccess enrol instance id.
     * @param callable $createuser Zero-argument callback returning the user id to enrol.
     * @param int|null $now Current time.
     * @return \stdClass ->status (enrolled|full|notenabled) and ->userid (0 when no account was created).
     */
    public static function reserve_and_enrol(int $enrolid, callable $createuser, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $instance = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'flexaccess'], '*', IGNORE_MISSING);
        if (!$instance || (int) $instance->status !== ENROL_INSTANCE_ENABLED) {
            return (object) ['status' => 'notenabled', 'userid' => 0];
        }
        $flex = instance_config::load($enrolid);
        $max = $flex ? (int) $flex->maxparticipants : 0;
        $enrolperiod = $flex ? (int) $flex->enrolperiod : 0;

        return capacity_service::run_with_lock(
            $enrolid,
            static function () use ($instance, $enrolid, $createuser, $max, $enrolperiod, $now): \stdClass {
                if (capacity_service::is_full($enrolid, $max, $now)) {
                    // No account is created here, so a lost capacity race cannot orphan a user.
                    return (object) ['status' => 'full', 'userid' => 0];
                }
                $userid = (int) $createuser();
                $plugin = enrol_get_plugin('flexaccess');
                $timeend = $enrolperiod > 0 ? $now + $enrolperiod : 0;
                // Enrol FlexAccess visitors under the dedicated participant role so that per-course
                // participant-list visibility can be enforced on it; fall back to the instance role
                // only if the dedicated role is somehow absent.
                $roleid = participant_role::get_id();
                if ($roleid === 0) {
                    $roleid = !empty($instance->roleid) ? (int) $instance->roleid : null;
                }
                $plugin->enrol_user($instance, $userid, $roleid, $now, $timeend, ENROL_USER_ACTIVE);
                // Apply the site-wide restrictions for anonymous FlexAccess visitors (messaging,
                // profile editing) via a system-context assignment of the dedicated role.
                participant_role::restrict($userid);
                participant_list_access::sync(
                    (int) $instance->courseid,
                    \enrol_flexaccess\api::get_effective_policy((int) $instance->courseid)->participantlistaccess
                );
                return (object) ['status' => 'enrolled', 'userid' => $userid];
            }
        );
    }
}
