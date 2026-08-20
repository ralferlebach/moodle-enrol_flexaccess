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
 * FlexAccess temporary-access controller.
 *
 * Composes the tested building blocks into the anonymous grant flow: evaluate the effective
 * policy and access window, check capacity, create a temporary user via the auth facade, enrol
 * it under the capacity lock, and schedule a persistence follow-up. Cross-plugin facade calls
 * are made lazily at runtime only (ADR-010).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/**
 * Orchestrates granting temporary access to a course.
 *
 * @package    enrol_flexaccess
 */
final class access_controller {
    /**
     * Sliding window for quick-registration rate limiting, in seconds.
     */
    private const QUICKREG_RATE_WINDOW = 600;

    /**
     * Maximum quick registrations per client address within the window (NAT-friendly).
     */
    private const QUICKREG_MAX_PER_IP = 30;

    /**
     * Read a positive integer plugin setting, falling back to a default when unset or non-positive.
     *
     * @param string $name Setting name within enrol_flexaccess.
     * @param int $default Fallback value.
     * @return int
     */
    private static function config_int(string $name, int $default): int {
        $value = (int) get_config('enrol_flexaccess', $name);
        return $value > 0 ? $value : $default;
    }

    /**
     * Default per-address cap for anonymous temporary-account creation within the window.
     */
    private const TEMP_MAX_PER_IP = 30;

    /**
     * Default sliding window (seconds) for temporary-account creation limits.
     */
    private const TEMP_RATE_WINDOW = 600;

    /**
     * Default site-wide circuit-breaker window (seconds).
     */
    private const TEMP_SITE_WINDOW = 3600;

    /**
     * Whether anonymous temporary-account creation is currently rate limited for this client.
     *
     * Applies an atomic per-address limit, a per-course+address limit, and an optional site-wide
     * circuit breaker (disabled when its maximum is zero). Each check records the attempt.
     *
     * @param int $courseid Target course id.
     * @param string $clientip Client address.
     * @param int $now Current time.
     * @return bool
     */
    private static function temporary_creation_limited(int $courseid, string $clientip, int $now): bool {
        $maxperip = self::config_int('tempmaxperip', self::TEMP_MAX_PER_IP);
        $window = self::config_int('tempwindow', self::TEMP_RATE_WINDOW);
        $sitemax = (int) get_config('enrol_flexaccess', 'tempsitemax');
        $sitewindow = self::config_int('tempsitewindow', self::TEMP_SITE_WINDOW);

        if ($sitemax > 0 && \auth_flexaccess\local\rate_limiter::hit('temp_site', 'site', $sitemax, $sitewindow, $now)) {
            return true;
        }
        if (\auth_flexaccess\local\rate_limiter::hit('temp_ip', $clientip, $maxperip, $window, $now)) {
            return true;
        }
        return \auth_flexaccess\local\rate_limiter::hit('temp_course_ip', $courseid . '|' . $clientip, $maxperip, $window, $now);
    }

    /**
     * Grant temporary access to a course for a new anonymous visitor.
     *
     * @param int $courseid Course id.
     * @param int|null $now Current time.
     * @param string|null $accesskey Clear-text access key, when the policy requires one.
     * @param string|null $clientip Client IP address used for rate limiting.
     * @return \stdClass Result with ->status (granted|closed|notallowed|badkey|notenabled|full|<enrolstatus>),
     *                   ->userid and ->enrolid (0 when not applicable).
     */
    public static function grant_temporary_access(
        int $courseid,
        ?int $now = null,
        ?string $accesskey = null,
        ?string $clientip = null
    ): \stdClass {
        $now = $now ?? time();
        $policy = \enrol_flexaccess\api::get_effective_policy($courseid);

        if (!access_gate::is_flexaccess_open($policy, $now)) {
            return self::result('closed');
        }
        if (!$policy->allowtemporary) {
            return self::result('notallowed');
        }
        // Server-side access-key enforcement: verified before any account is created, so it cannot
        // be bypassed by calling the controller directly. The key is never persisted or logged here.
        if ($policy->temporaryaccesskeyscope !== 'none') {
            if (
                $accesskey === null || $accesskey === ''
                    || !access_key_service::verify($courseid, $policy, $accesskey)
            ) {
                return self::result('badkey');
            }
        }
        // General, atomic rate limit on anonymous account creation, independent of the access key.
        // A correct key does not license mass creation; limits are per client address, per
        // course+address, and an optional site-wide circuit breaker. Checked after the key so failed
        // key attempts are not counted as creations.
        if ($clientip !== null && self::temporary_creation_limited($courseid, $clientip, $now)) {
            return self::result('ratelimited');
        }
        $enrolid = self::enabled_instance($courseid);
        if ($enrolid === 0) {
            return self::result('notenabled');
        }
        $active = \enrol_flexaccess\api::get_active_enrolment_count($courseid, $now);
        if (!capacity_service::has_free_capacity($active, $policy->maxparticipants)) {
            return self::result('full');
        }

        $timeexpires = $policy->temporarylifetime > 0 ? $now + $policy->temporarylifetime : null;
        $outcome = enrol_service::reserve_and_enrol(
            $enrolid,
            static fn(): int => \auth_flexaccess\api::create_temporary_user($timeexpires, $courseid, null, $now),
            $now
        );
        if ($outcome->status !== 'enrolled') {
            return self::result($outcome->status, $outcome->userid, $enrolid);
        }

        return self::result('granted', $outcome->userid, $enrolid);
    }

    /**
     * Grant access by quick registration: create a provisional temporary account, enrol it, and bind
     * the upgrade to a full account to email verification (immediate conversion only when verification
     * is disabled site-wide).
     *
     * The created account has the person's own email and password, so unlike temporary access it
     * survives logout and can be used to log in again later.
     *
     * @param int $courseid Course id.
     * @param \stdClass $userdata Object with email, firstname, lastname and password.
     * @param string|null $clientip Client address for rate limiting, or null to skip it.
     * @param int|null $now Current time.
     * @param bool|false $trustedgate Trusted gate flag. 
     * @return \stdClass Result with ->status and, on success, ->userid and ->enrolid.
     */
    public static function grant_quick_registration(
        int $courseid,
        \stdClass $userdata,
        ?string $clientip = null,
        ?int $now = null,
        bool $trustedgate = false
    ): \stdClass {
        $now = $now ?? time();
        $policy = \enrol_flexaccess\api::get_effective_policy($courseid);

        if (!access_gate::is_flexaccess_open($policy, $now)) {
            return self::result('closed');
        }
        if (!$policy->allowquick) {
            return self::result('notallowed');
        }
        // Additional access gate (shared password or allowed email domain), on top of email
        // activation. Instance settings override the site default (resolved in the policy). A
        // trusted caller (a campaign or a person-bound invitation) has already authorised the
        // applicant through its own gate and is an alternative provisioning path, so the course
        // gate is not applied again.
        if (!$trustedgate) {
            $accesspassword = (string) ($userdata->accesspassword ?? '');
            if (!quickreg_gate::passes($policy, (string) $userdata->email, $accesspassword)) {
                return self::result('badgate');
            }
        }
        // Pre-validate the email before any account is created, so a syntactically invalid or
        // already-taken address is rejected up front instead of leaving an enrolled orphan account
        // (which would also hold a capacity slot). A residual race is compensated below.
        $email = \core_text::strtolower(trim((string) ($userdata->email ?? '')));
        if ($email === '' || !validate_email($email)) {
            return self::result('invalidemail');
        }
        if (\auth_flexaccess\api::email_available($email) === false) {
            return self::result('emailtaken');
        }
        // Throttle anonymous account creation per client address. The limit is generous so a whole
        // class behind one NAT address is not blocked, but scripted mass-creation is slowed. Limits
        // are admin-configurable; the constants are the fallback defaults.
        $maxperip = self::config_int('quickregmaxperip', self::QUICKREG_MAX_PER_IP);
        $window = self::config_int('quickregwindow', self::QUICKREG_RATE_WINDOW);
        if (
            $clientip !== null && \auth_flexaccess\local\rate_limiter::hit(
                'quickreg',
                $clientip,
                $maxperip,
                $window,
                $now
            )
        ) {
            return self::result('ratelimited');
        }
        $enrolid = self::enabled_instance($courseid);
        if ($enrolid === 0) {
            return self::result('notenabled');
        }
        $active = \enrol_flexaccess\api::get_active_enrolment_count($courseid, $now);
        if (!capacity_service::has_free_capacity($active, $policy->maxparticipants)) {
            return self::result('full');
        }

        $provisionalexpiry = $now + ($policy->provisionallifetime > 0 ? $policy->provisionallifetime : DAYSECS);
        $outcome = enrol_service::reserve_and_enrol(
            $enrolid,
            static fn(): int => \auth_flexaccess\api::create_temporary_user($provisionalexpiry, $courseid, null, $now),
            $now
        );
        if ($outcome->status !== 'enrolled') {
            return self::result($outcome->status, $outcome->userid, $enrolid);
        }

        // Bind the upgrade to a real, verified identity: the provisional account only becomes a
        // regular authenticated account once the emailed activation link is followed. When email
        // verification is disabled site-wide the funnel converts immediately.
        $status = \auth_flexaccess\api::request_persistence(
            $outcome->userid,
            (string) $userdata->email,
            (string) $userdata->firstname,
            (string) $userdata->lastname,
            (string) $userdata->password,
            $now
        );
        if ($status === 'verificationsent') {
            return self::result('verificationsent', $outcome->userid, $enrolid);
        }
        if ($status === 'converted') {
            return self::result('granted', $outcome->userid, $enrolid);
        }
        // Persistence setup failed after the account was created and enrolled (e.g. a residual
        // email-availability race). Compensate by removing the enrolment and deleting the temporary
        // user, so no orphaned account is left holding a capacity slot.
        \auth_flexaccess\api::rollback_temporary_user((int) $outcome->userid);
        return self::result($status, 0, $enrolid);
    }

    /**
     * Find the first enabled FlexAccess enrol instance of a course.
     *
     * @param int $courseid Course id.
     * @return int Enrol instance id, or 0 when none is enabled.
     */
    private static function enabled_instance(int $courseid): int {
        global $DB;
        $record = $DB->get_record('enrol', [
            'enrol' => 'flexaccess',
            'courseid' => $courseid,
            'status' => ENROL_INSTANCE_ENABLED,
        ], 'id', IGNORE_MULTIPLE);
        return $record ? (int) $record->id : 0;
    }

    /**
     * Build a result object.
     *
     * @param string $status Result status.
     * @param int $userid Created user id, if any.
     * @param int $enrolid Enrol instance id, if any.
     * @return \stdClass
     */
    private static function result(string $status, int $userid = 0, int $enrolid = 0): \stdClass {
        return (object) ['status' => $status, 'userid' => $userid, 'enrolid' => $enrolid];
    }
}
