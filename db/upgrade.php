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
 * Upgrade steps for enrol_flexaccess.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Apply enrol_flexaccess database upgrades.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 *
 * @package    enrol_flexaccess
 */
function xmldb_enrol_flexaccess_upgrade($oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026081730) {
        $table = new xmldb_table('enrol_flexaccess_instance');

        $availablefrom = new xmldb_field(
            'availablefrom',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'enrolperiod'
        );
        if (!$dbman->field_exists($table, $availablefrom)) {
            $dbman->add_field($table, $availablefrom);
        }

        $availableuntil = new xmldb_field(
            'availableuntil',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'availablefrom'
        );
        if (!$dbman->field_exists($table, $availableuntil)) {
            $dbman->add_field($table, $availableuntil);
        }

        $maxparticipants = new xmldb_field(
            'maxparticipants',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'availableuntil'
        );
        if (!$dbman->field_exists($table, $maxparticipants)) {
            $dbman->add_field($table, $maxparticipants);
        }

        upgrade_plugin_savepoint(true, 2026081730, 'enrol', 'flexaccess');
    }

    return true;
}
