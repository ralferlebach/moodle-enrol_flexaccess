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
 *
 * @package    enrol_flexaccess
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
        $allowtemporary = !empty($data['allowtemporary']) ? 1 : 0;
        $allowquick = !empty($data['allowquick']) ? 1 : 0;
        $allowguest = !empty($data['allowguest']) ? 1 : 0;
        $allownormallogin = isset($data['allownormallogin']) ? (!empty($data['allownormallogin']) ? 1 : 0) : 1;
        $temporarylifetime = max(0, (int) ($data['temporarylifetime'] ?? 0));
        $enrolperiod = max(0, (int) ($data['enrolperiod'] ?? 0));
        $expiryactionraw = (string) ($data['expiryaction'] ?? 'suspend');
        $expiryaction = in_array($expiryactionraw, ['suspend', 'unenrol'], true) ? $expiryactionraw : 'suspend';
        $keymoderaw = (string) ($data['temporaryaccesskeymode'] ?? 'inherit');
        $keymode = in_array($keymoderaw, ['inherit', 'course'], true) ? $keymoderaw : 'inherit';
        $visraw = (string) ($data['participantvisibility'] ?? 'inherit');
        $participantvisibility = in_array($visraw, ['inherit', 'show', 'hide'], true) ? $visraw : 'inherit';
        $gateraw = (string) ($data['quickreggatemode'] ?? 'inherit');
        $quickreggatemode = in_array($gateraw, ['inherit', 'none', 'password', 'domain'], true) ? $gateraw : 'inherit';
        $quickreggatedomains = trim((string) ($data['quickreggatedomains'] ?? ''));

        $fields = [
            'availablefrom' => $availablefrom,
            'availableuntil' => $availableuntil,
            'maxparticipants' => $maxparticipants,
            'allowtemporary' => $allowtemporary,
            'allowquick' => $allowquick,
            'allowguest' => $allowguest,
            'allownormallogin' => $allownormallogin,
            'expiryaction' => $expiryaction,
            'enrolperiod' => $enrolperiod,
            'temporaryaccesskeymode' => $keymode,
            'participantvisibility' => $participantvisibility,
            'quickreggatemode' => $quickreggatemode,
            'quickreggatedomains' => $quickreggatedomains,
        ];
        // Only overwrite temporarylifetime when the form supplied one (0 keeps the stored default).
        if ($temporarylifetime > 0) {
            $fields['temporarylifetime'] = $temporarylifetime;
        }
        // Hash a newly entered course key; an empty field leaves any existing hash untouched. When the
        // mode is not "course" the gate is inactive regardless of the stored hash.
        $newkey = trim((string) ($data['temporaryaccesskey'] ?? ''));
        if ($keymode === 'course' && $newkey !== '') {
            $fields['temporaryaccesskeyhash'] = password_hash($newkey, PASSWORD_DEFAULT);
        }
        // Hash a newly entered quick-registration gate password; empty leaves any existing hash.
        $newgatepw = trim((string) ($data['quickreggatepassword'] ?? ''));
        if ($quickreggatemode === 'password' && $newgatepw !== '') {
            $fields['quickreggatepasswordhash'] = quickreg_gate::hash($newgatepw);
        }

        $existing = self::load($enrolid);
        if ($existing) {
            foreach ($fields as $key => $value) {
                $existing->$key = $value;
            }
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
        } else {
            $record = (object) array_merge($fields, [
                'enrolid' => $enrolid,
                'timemodified' => $now,
            ]);
            $DB->insert_record(self::TABLE, $record);
        }

        self::sync_participant_visibility($enrolid);
    }

    /**
     * Apply the effective participant-list visibility to the dedicated role override for the course.
     *
     * @param int $enrolid Core enrol instance id.
     * @return void
     */
    private static function sync_participant_visibility(int $enrolid): void {
        global $DB;
        $courseid = (int) $DB->get_field('enrol', 'courseid', ['id' => $enrolid]);
        if ($courseid === 0) {
            return;
        }
        $policy = \enrol_flexaccess\api::get_effective_policy($courseid);
        participant_visibility::sync($courseid, $policy->participantvisibility);
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
