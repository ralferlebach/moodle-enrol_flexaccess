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

namespace enrol_flexaccess\local;

/**
 * Short-lived, server-side proof that a visitor has passed a course access gate.
 *
 * The gate is answered in its own step; the actual entry form follows only afterwards. Between the
 * two steps the clear-text course password must not travel anywhere - not in the URL, not in a
 * hidden field, not in the referrer. Instead the successful check leaves this pass in the visitor's
 * session. It records no secret, only:
 *
 *  - the course and the gate purpose it was issued for (a pass for one course or purpose is useless
 *    for another);
 *  - the session it was issued in (a copied cookie from another session does not carry it);
 *  - a fingerprint of the gate secret (changing the course password invalidates open passes);
 *  - an expiry of {@see self::TTL} seconds.
 *
 * The pass is consumed when the protected action succeeds, so it cannot be replayed.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class gate_pass {
    /** Purpose: the password gate in front of quick registration. */
    public const PURPOSE_QUICKREG = 'quickreg';

    /** Lifetime of a pass in seconds: enough to fill in the registration form. */
    public const TTL = 900;

    /** Session key holding the passes. */
    private const KEY = 'enrol_flexaccess_gatepass';

    /**
     * Issue a pass after a successful gate check.
     *
     * @param int $courseid Course id.
     * @param string $purpose Gate purpose.
     * @param string $secret Stored gate secret (hash) the pass is bound to.
     * @param int|null $now Current time.
     * @return void
     */
    public static function issue(int $courseid, string $purpose, string $secret, ?int $now = null): void {
        global $SESSION;
        $now = $now ?? time();
        $passes = self::all();
        $passes[self::slot($courseid, $purpose)] = [
            'courseid' => $courseid,
            'purpose' => $purpose,
            'session' => self::session_fingerprint(),
            'secret' => sha1($secret),
            'expires' => $now + self::TTL,
        ];
        $SESSION->{self::KEY} = $passes;
    }

    /**
     * Whether a valid pass exists for this course, purpose, session and gate secret.
     *
     * @param int $courseid Course id.
     * @param string $purpose Gate purpose.
     * @param string $secret Current gate secret (hash).
     * @param int|null $now Current time.
     * @return bool
     */
    public static function is_valid(int $courseid, string $purpose, string $secret, ?int $now = null): bool {
        $now = $now ?? time();
        $pass = self::all()[self::slot($courseid, $purpose)] ?? null;
        if (!is_array($pass) || $secret === '') {
            return false;
        }
        return (int) $pass['courseid'] === $courseid
            && $pass['purpose'] === $purpose
            && hash_equals((string) $pass['session'], self::session_fingerprint())
            && hash_equals((string) $pass['secret'], sha1($secret))
            && (int) $pass['expires'] > $now;
    }

    /**
     * Remove the pass (after the protected action succeeded, or to reset the flow).
     *
     * @param int $courseid Course id.
     * @param string $purpose Gate purpose.
     * @return void
     */
    public static function consume(int $courseid, string $purpose): void {
        global $SESSION;
        $passes = self::all();
        unset($passes[self::slot($courseid, $purpose)]);
        $SESSION->{self::KEY} = $passes;
    }

    /**
     * All passes of this session.
     *
     * @return array
     */
    private static function all(): array {
        global $SESSION;
        return isset($SESSION->{self::KEY}) && is_array($SESSION->{self::KEY}) ? $SESSION->{self::KEY} : [];
    }

    /**
     * Storage slot of a pass.
     *
     * @param int $courseid Course id.
     * @param string $purpose Gate purpose.
     * @return string
     */
    private static function slot(int $courseid, string $purpose): string {
        return $purpose . ':' . $courseid;
    }

    /**
     * Fingerprint of the current session id (never stored in clear).
     *
     * @return string
     */
    private static function session_fingerprint(): string {
        return sha1('enrol_flexaccess_gatepass|' . session_id());
    }
}
