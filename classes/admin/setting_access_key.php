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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Write-only administration setting for a shared FlexAccess access key.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\admin;

/**
 * Stores only a password hash and never renders an existing hash back into the form.
 */
final class setting_access_key extends \admin_setting_configtext {
    /**
     * Return an empty value so an existing hash is never displayed.
     *
     * @return string
     */
    public function get_setting(): string {
        return '';
    }

    /**
     * Hash a newly entered access key. Blank input means "leave unchanged".
     *
     * Disabling the separate `temporaryaccesskeyrequired` setting turns the gate off without deleting the hash.
     *
     * @param mixed $data New clear-text key from the administration form.
     * @return string Empty string on success, error text otherwise.
     */
    public function write_setting($data): string {
        $candidate = (string) $data;
        if ($candidate === '') {
            return '';
        }
        $hash = password_hash($candidate, PASSWORD_DEFAULT);
        if ($hash === false || !$this->config_write($this->name, $hash)) {
            return get_string('errorsetting', 'admin');
        }
        return '';
    }
}
