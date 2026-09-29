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
 * Restore of the FlexAccess enrolment method configuration.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_enrol_flexaccess_plugin extends restore_enrol_plugin {
    /**
     * Paths of the FlexAccess configuration below the enrol element.
     *
     * @return restore_path_element[]
     */
    protected function define_enrol_plugin_structure() {
        return [new restore_path_element('enrol_flexaccess_config', $this->connectionpoint->get_path() . '/flexaccessconfig')];
    }

    /**
     * Write the backed-up configuration onto the restored FlexAccess method.
     *
     * Only for a method this restore has created: when the backup is merged into a course that already
     * has a FlexAccess method, that method's own configuration is kept. Role and group ids are mapped
     * to the target; an unmapped group is dropped, an unmapped role falls back to the participant role.
     *
     * @param array|stdClass $data Backed-up row.
     * @return void
     */
    public function process_enrol_flexaccess_config($data): void {
        global $DB;
        $data = (object) $data;
        $enrolid = (int) $this->get_new_parentid('enrol');
        $created = (int) $this->get_mappingid('enrol_flexaccess_created', (int) $this->get_old_parentid('enrol'));
        if ($enrolid <= 0 || $created !== $enrolid) {
            return;
        }
        unset($data->id);
        $data->enrolid = $enrolid;
        $role = !empty($data->roleid) ? $this->get_mappingid('role', (int) $data->roleid) : false;
        $data->roleid = $role ? (int) $role : \enrol_flexaccess\local\participant_role::get_id();
        $group = !empty($data->groupid) ? $this->get_mappingid('group', (int) $data->groupid) : false;
        $data->groupid = $group ? (int) $group : 0;
        $data->timemodified = time();
        $existing = $DB->get_field('enrol_flexaccess_instance', 'id', ['enrolid' => $enrolid]);
        if ($existing) {
            $data->id = (int) $existing;
            $DB->update_record('enrol_flexaccess_instance', $data);
        } else {
            $DB->insert_record('enrol_flexaccess_instance', $data);
        }
        \cache::make('enrol_flexaccess', 'policy')->purge();
    }
}
