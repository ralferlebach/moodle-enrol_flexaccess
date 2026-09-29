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
 * Combines an effective policy with the current time and capacity into offerable methods.
 *
 * The access window gates the FlexAccess methods (temporary, quick registration, guest); it
 * does not affect normal Moodle login. Capacity gates only the methods that create a new
 * FlexAccess enrolment (temporary, quick registration). Normal login is never gated.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/**
 * Runtime decision helper for offerable access methods.
 *
 * @package    enrol_flexaccess
 */
final class access_gate {
    /**
     * Whether FlexAccess methods are within their access window.
     *
     * @param policy $policy Effective policy.
     * @param int $now Current time.
     * @return bool
     */
    public static function is_flexaccess_open(policy $policy, int $now): bool {
        return self::plugins_enabled() && $policy->is_within_window($now);
    }

    /**
     * Kill switch: FlexAccess entry flows only run while both halves of the mechanism are enabled.
     *
     * With the enrolment plugin disabled, Moodle ignores its enrolments; with the authentication
     * plugin disabled, the accounts it creates could not sign in again. In either case an entry flow
     * must stop before creating any user, account, enrolment or mail - not afterwards.
     *
     * @return bool
     */
    public static function plugins_enabled(): bool {
        return enrol_is_enabled('flexaccess') && is_enabled_auth('flexaccess');
    }

    /**
     * Determine which access methods can be offered right now.
     *
     * @param policy $policy Effective policy.
     * @param int $now Current time.
     * @param int $activecount Current number of active FlexAccess enrolments for the instance.
     * @return \stdClass Object with boolean properties: temporary, quick, guest, normallogin.
     */
    public static function offerable(policy $policy, int $now, int $activecount): \stdClass {
        $open = self::is_flexaccess_open($policy, $now);
        $hascapacity = capacity_service::has_free_capacity($activecount, $policy->maxparticipants);

        $result = new \stdClass();
        $result->temporary = $policy->allowtemporary && $open && $hascapacity;
        $result->quick = $policy->allowquick && $open && $hascapacity;
        $result->guest = $policy->allowguest && $open;
        $result->normallogin = $policy->allownormallogin && self::plugins_enabled();
        return $result;
    }

    /**
     * Whether at least one access method is offerable right now.
     *
     * @param policy $policy Effective policy.
     * @param int $now Current time.
     * @param int $activecount Current number of active FlexAccess enrolments.
     * @return bool
     */
    public static function has_any_method(policy $policy, int $now, int $activecount): bool {
        $o = self::offerable($policy, $now, $activecount);
        return $o->temporary || $o->quick || $o->guest || $o->normallogin;
    }
}
