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
 * Immutable-ish policy value object for enrol_flexaccess.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/**
 * Effective FlexAccess access policy.
 *
 * @package    enrol_flexaccess
 */
final class policy {
    /** @var bool */ public bool $allowtemporary = false;
    /** @var bool */ public bool $allowquick = false;
    /** @var bool */ public bool $allowguest = false;
    /** @var bool */ public bool $allownormallogin = true;
    /** @var int */ public int $temporarylifetime = 21600;
    /** @var int */ public int $provisionallifetime = 172800;
    /** @var string */ public string $participantvisibility = 'show';
    /** @var bool */ public bool $temporaryaccesskeyrequired = false;
    /** @var string */ public string $temporaryaccesskeyscope = 'none';
    /** @var int Access window start; 0 = no lower bound. */ public int $availablefrom = 0;
    /** @var int Access window end; 0 = no upper bound. */ public int $availableuntil = 0;
    /** @var int Maximum active FlexAccess enrolments; 0 = unlimited. */ public int $maxparticipants = 0;
    /** @var string Quick-registration access gate: none|password|domain. */ public string $quickreggatemode = 'none';
    /** @var string Hashed shared password for the password gate (empty when unset). */
    public string $quickreggatepasswordhash = '';
    /** @var string Newline/comma separated allowed email domains for the domain gate. */ public string $quickreggatedomains = '';

    /**
     * Whether the configured access window is open at the given time.
     *
     * The access window is independent of and combinable with the access key, and is
     * distinct from account lifetime and enrolment lifetime.
     *
     * @param int $now Unix timestamp to evaluate against.
     * @return bool
     */
    public function is_within_window(int $now): bool {
        return access_window::is_open($this->availablefrom, $this->availableuntil, $now);
    }

    /**
     * Whether this instance has an unlimited participant capacity.
     *
     * @return bool
     */
    public function is_capacity_unlimited(): bool {
        return $this->maxparticipants <= 0;
    }
}
