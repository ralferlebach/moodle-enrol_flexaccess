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
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/** Effective FlexAccess access policy. */
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
}
