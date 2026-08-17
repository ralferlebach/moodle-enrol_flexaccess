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
 * Policy precedence helper for enrol_flexaccess.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/**
 * Resolves policy layers without widening higher-level prohibitions by default.
 */
final class policy_resolver {
    /**
     * Merge boolean access-method layers.
     *
     * Null means inherit. A false higher-level value stays false unless widening is explicitly allowed.
     *
     * @param bool $parent Parent value.
     * @param bool|null $child Child override.
     * @param bool $allowwidening Whether child may re-enable a parent prohibition.
     * @return bool
     */
    public static function merge_permission(bool $parent, ?bool $child, bool $allowwidening = false): bool {
        if ($child === null) {
            return $parent;
        }
        if (!$parent && $child && !$allowwidening) {
            return false;
        }
        return $child;
    }

    /**
     * Resolve course participant visibility.
     *
     * @param string $systemdefault show|hide.
     * @param string $coursevalue inherit|show|hide.
     * @return string
     */
    public static function participant_visibility(string $systemdefault, string $coursevalue): string {
        if (!in_array($systemdefault, ['show', 'hide'], true)) {
            throw new \coding_exception('Invalid system participant visibility.');
        }
        if (!in_array($coursevalue, ['inherit', 'show', 'hide'], true)) {
            throw new \coding_exception('Invalid course participant visibility.');
        }
        return $coursevalue === 'inherit' ? $systemdefault : $coursevalue;
    }

    /**
     * Resolve the effective temporary-user access-key scope.
     *
     * A course may inherit the system state or require its own key. It may not remove a required system key.
     *
     * @param bool $systemrequired Whether a system-wide access key is required.
     * @param string $coursevalue inherit|course.
     * @return string none|system|course.
     */
    public static function temporary_access_key_scope(bool $systemrequired, string $coursevalue): string {
        if (!in_array($coursevalue, ['inherit', 'course'], true)) {
            throw new \coding_exception('Invalid course temporary access-key mode.');
        }
        if ($coursevalue === 'course') {
            return 'course';
        }
        return $systemrequired ? 'system' : 'none';
    }
}
