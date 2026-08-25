<?php
// This file is part of Moodle - https://moodle.org/
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

namespace enrol_flexaccess\check;

use core\check\check;
use core\check\result;

/**
 * Status check for the auth_flexaccess <-> enrol_flexaccess enable coupling.
 *
 * The two plugins are installed together (hard dependency) but Moodle enables auth and enrol
 * plugins independently. Only when both are enabled does FlexAccess work end to end. This check
 * surfaces contradictory enable states, which are otherwise silent and easy to miss.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coupling extends check {
    /**
     * Get the short human-readable name for this check.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('check:coupling', 'enrol_flexaccess');
    }

    /**
     * A link to the enrol plugin management screen, where the enable state can be corrected.
     *
     * @return \action_link|null
     */
    public function get_action_link(): ?\action_link {
        $url = new \moodle_url('/admin/settings.php', ['section' => 'manageenrols']);
        return new \action_link($url, get_string('check:coupling:action', 'enrol_flexaccess'));
    }

    /**
     * Evaluate the coupling and return the corresponding result.
     *
     * @return result
     */
    public function get_result(): result {
        $authenabled = in_array('flexaccess', array_filter(explode(',', (string) get_config(null, 'auth'))), true);
        $enrolenabled = array_key_exists('flexaccess', enrol_get_plugins(true));

        if ($authenabled && $enrolenabled) {
            return new result(result::OK, get_string('check:coupling:ok', 'enrol_flexaccess'));
        }
        if (!$authenabled && !$enrolenabled) {
            // The whole ecosystem is switched off: nothing to warn about.
            return new result(result::NA, get_string('check:coupling:bothoff', 'enrol_flexaccess'));
        }
        if ($enrolenabled && !$authenabled) {
            // The dangerous state: enrol provisions flexaccess-auth accounts that then cannot log in.
            return new result(
                result::ERROR,
                get_string('check:coupling:enrolonly', 'enrol_flexaccess'),
                get_string('check:coupling:enrolonly_detail', 'enrol_flexaccess')
            );
        }
        // Auth enabled, enrol disabled: inert - no course offers FlexAccess entry.
        return new result(
            result::WARNING,
            get_string('check:coupling:authonly', 'enrol_flexaccess'),
            get_string('check:coupling:authonly_detail', 'enrol_flexaccess')
        );
    }
}
