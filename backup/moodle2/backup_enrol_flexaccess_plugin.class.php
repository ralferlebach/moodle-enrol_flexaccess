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
 * Backup of the FlexAccess enrolment method configuration.
 *
 * Core backs up the enrol row itself; the FlexAccess-specific configuration (access methods, gates,
 * windows, capacity) lives in enrol_flexaccess_instance and is appended here, so a restored,
 * duplicated or imported course keeps a working FlexAccess method. Secrets are only ever stored as
 * hashes, and they are carried over as such.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_enrol_flexaccess_plugin extends backup_enrol_plugin {
    /**
     * Append the FlexAccess configuration to the enrol element.
     *
     * @return backup_plugin_element
     */
    protected function define_enrol_plugin_structure() {
        $plugin = $this->get_plugin_element();
        $config = new backup_nested_element('flexaccessconfig', ['id'], [
            'allowtemporary',
            'allowquick',
            'allowguest',
            'allownormallogin',
            'allowmagiclogin',
            'temporarylifetime',
            'provisionallifetime',
            'enrolperiod',
            'availablefrom',
            'availableuntil',
            'maxparticipants',
            'expiryaction',
            'roleid',
            'groupid',
            'participantlistaccess',
            'temporaryaccesskeymode',
            'temporaryaccesskeyhash',
            'quickreggatemode',
            'quickreggatepasswordhash',
            'quickreggatedomains',
            'profilefieldsjson',
            'timemodified',
        ]);
        $plugin->add_child($config);
        $config->set_source_table('enrol_flexaccess_instance', ['enrolid' => backup::VAR_PARENTID]);
        $config->annotate_ids('role', 'roleid');
        $config->annotate_ids('group', 'groupid');
        return $plugin;
    }
}
