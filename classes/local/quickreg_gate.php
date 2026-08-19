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
 * Additional access gate for public quick registration.
 *
 * On top of email activation, a course (or the site default) may require either a shared password
 * or that the applicant's email belongs to an allowed domain. Instance settings override the system
 * defaults. The shared password is only ever compared as a hash; the clear value is never stored.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quickreg_gate {
    /**
     * Hash a shared gate password for storage.
     *
     * @param string $password Clear password.
     * @return string Hash, or empty string for an empty password.
     */
    public static function hash(string $password): string {
        $password = trim($password);
        return $password === '' ? '' : password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Whether a quick-registration attempt satisfies the effective gate.
     *
     * @param policy $policy Effective policy carrying the resolved gate configuration.
     * @param string $email Applicant email address.
     * @param string $password Shared password supplied by the applicant (password gate only).
     * @return bool
     */
    public static function passes(policy $policy, string $email, string $password): bool {
        switch ($policy->quickreggatemode) {
            case 'password':
                $expected = trim($policy->quickreggatepasswordhash);
                if ($expected === '') {
                    // Password gate selected but none configured: fail closed.
                    return false;
                }
                return password_verify($password, $expected);
            case 'domain':
                return self::domain_allowed($email, $policy->quickreggatedomains);
            default:
                return true;
        }
    }

    /**
     * Whether the email's domain is in the allowed list.
     *
     * @param string $email Email address.
     * @param string $domains Newline/comma separated domain list.
     * @return bool
     */
    private static function domain_allowed(string $email, string $domains): bool {
        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }
        $emaildomain = \core_text::strtolower(trim(substr($email, $at + 1)));
        if ($emaildomain === '') {
            return false;
        }
        $allowed = preg_split('/[\s,]+/', \core_text::strtolower($domains), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($allowed as $domain) {
            $domain = ltrim(trim($domain), '@');
            if ($domain !== '' && ($emaildomain === $domain || str_ends_with($emaildomain, '.' . $domain))) {
                return true;
            }
        }
        return false;
    }
}
