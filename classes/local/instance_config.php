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
 * Persistence for the extended FlexAccess enrolment-instance configuration.
 *
 * This iteration owns the access window and participant capacity. Columns not written here
 * keep their database defaults on insert and their existing values on update.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/**
 * Reads and writes the enrol_flexaccess_instance extension row.
 */
final class instance_config {
    /**
     * Extension table name.
     */
    private const TABLE = 'enrol_flexaccess_instance';

    /**
     * Load the extension row for an enrol instance.
     *
     * @param int $enrolid Core enrol instance id.
     * @return \stdClass|null
     */
    public static function load(int $enrolid): ?\stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['enrolid' => $enrolid]);
        return $record ?: null;
    }

    /**
     * Insert or update the access-window and capacity fields for an enrol instance.
     *
     * @param int $enrolid Core enrol instance id.
     * @param array $data Form/field data; recognised keys: availablefrom, availableuntil, maxparticipants.
     * @return void
     */
    public static function save(int $enrolid, array $data): void {
        global $DB;
        $now = time();
        $availablefrom = max(0, (int) ($data['availablefrom'] ?? 0));
        $availableuntil = max(0, (int) ($data['availableuntil'] ?? 0));
        $maxparticipants = max(0, (int) ($data['maxparticipants'] ?? 0));

        $existing = self::load($enrolid);
        if ($existing) {
            $existing->availablefrom = $availablefrom;
            $existing->availableuntil = $availableuntil;
            $existing->maxparticipants = $maxparticipants;
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
            return;
        }

        $record = (object) [
            'enrolid' => $enrolid,
            'availablefrom' => $availablefrom,
            'availableuntil' => $availableuntil,
            'maxparticipants' => $maxparticipants,
            'timemodified' => $now,
        ];
        $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Delete the extension row for an enrol instance.
     *
     * @param int $enrolid Core enrol instance id.
     * @return void
     */
    public static function delete(int $enrolid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['enrolid' => $enrolid]);
    }
}
