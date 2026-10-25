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
 * Write contract (issue #13):
 *  - save() is a patch. Only the keys present (and not null) in $data are changed; every other field
 *    keeps its stored value, or the database default when the row is created. An explicit 0 clears a
 *    limit (access window bound, capacity, enrolment period).
 *  - The edit form is a full replace of the fields it shows: it always posts every one of them (an
 *    unticked checkbox or optional date arrives as an explicit 0). from_form() turns the submitted
 *    form into that complete patch and keeps a stored time the form could not show exactly.
 *  - Nothing is written when any value is invalid or the merged access window is not a valid range.
 *    A patch that changes nothing writes nothing (idempotent).
 *  - Secrets: a non-empty course key or gate password is hashed; an empty one keeps the stored hash.
 *  - The enrolment period is mirrored into the core enrol record, so the core form value (set_data
 *    of the enrol row) and the FlexAccess value cannot drift apart.
 *  - After a write the course policy cache is purged before the participant-list override is synced,
 *    so the effective policy reflects the saved state immediately.
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
     * Fields of the patch contract with their kind (or list of allowed values).
     */
    private const FIELDS = [
        'availablefrom' => 'timestamp',
        'availableuntil' => 'timestamp',
        'maxparticipants' => 'count',
        'allowtemporary' => 'flag',
        'allowquick' => 'flag',
        'allowguest' => 'flag',
        'allownormallogin' => 'flag',
        'allowmagiclogin' => 'flag',
        'temporarylifetime' => 'lifetime',
        'enrolperiod' => 'count',
        'expiryaction' => ['suspend', 'unenrol'],
        'temporaryaccesskeymode' => ['inherit', 'course'],
        'participantlistaccess' => ['inherit', 'show', 'hide'],
        'quickreggatemode' => ['inherit', 'none', 'password', 'domain'],
        'quickreggatedomains' => 'text',
    ];

    /**
     * Database defaults of the contract fields (db/install.xml), used when a row is created.
     */
    private const DEFAULTS = [
        'availablefrom' => 0,
        'availableuntil' => 0,
        'maxparticipants' => 0,
        'allowtemporary' => 0,
        'allowquick' => 0,
        'allowguest' => 0,
        'allownormallogin' => 1,
        'allowmagiclogin' => 1,
        'temporarylifetime' => 21600,
        'enrolperiod' => 0,
        'expiryaction' => 'suspend',
        'temporaryaccesskeymode' => 'inherit',
        'participantlistaccess' => 'inherit',
        'quickreggatemode' => 'inherit',
        'quickreggatedomains' => '',
    ];

    /**
     * Plain-text secrets: input key => [hash column, mode field, mode value in which it applies].
     */
    private const SECRETS = [
        'temporaryaccesskey' => ['temporaryaccesskeyhash', 'temporaryaccesskeymode', 'course'],
        'quickreggatepassword' => ['quickreggatepasswordhash', 'quickreggatemode', 'password'],
    ];

    /**
     * Columns only a restore may write verbatim (already hashed or mapped by the restore step).
     */
    private const RESTORE_COLUMNS = ['temporaryaccesskeyhash', 'quickreggatepasswordhash', 'provisionallifetime',
        'roleid', 'groupid', 'profilefieldsjson'];

    /**
     * Fields the instance edit form shows; the form always posts all of them.
     */
    public const FORM_FIELDS = ['availablefrom', 'availableuntil', 'maxparticipants', 'participantlistaccess',
        'allowtemporary', 'allowquick', 'allowguest', 'allownormallogin', 'allowmagiclogin', 'temporarylifetime',
        'enrolperiod', 'expiryaction', 'temporaryaccesskeymode', 'quickreggatemode', 'quickreggatedomains',
        'temporaryaccesskey', 'quickreggatepassword'];

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
     * Apply a patch to the instance configuration (see the write contract in the file header).
     *
     * @param int $enrolid Core enrol instance id.
     * @param array $data Patch: contract fields, plus the plain-text secrets temporaryaccesskey and
     *        quickreggatepassword. Unknown keys (core enrol fields, form buttons) are ignored.
     * @return bool Whether anything was written.
     * @throws \invalid_parameter_exception When a value or the merged access window is invalid.
     */
    public static function save(int $enrolid, array $data): bool {
        $changes = self::changes($enrolid, $data);
        return self::write($enrolid, $changes);
    }

    /**
     * Validate a patch against the stored row without writing anything.
     *
     * @param int $enrolid Core enrol instance id (0 for an instance that does not exist yet).
     * @param array $data Patch as for save().
     * @return void
     * @throws \invalid_parameter_exception When a value or the merged access window is invalid.
     */
    public static function validate(int $enrolid, array $data): void {
        self::changes($enrolid, $data);
    }

    /**
     * Turn a submitted instance edit form into the complete patch of the fields it shows.
     *
     * The date selectors show minutes in the user's time zone. A submitted time that shows exactly
     * like the stored one (same local minute) keeps the stored value, so opening and saving the form
     * never moves a time that carries seconds or lies in the repeated hour of a DST change.
     *
     * @param int $enrolid Core enrol instance id (0 when the instance is being created).
     * @param array $data Submitted form data (get_data()).
     * @return array Patch for save().
     */
    public static function from_form(int $enrolid, array $data): array {
        $patch = [];
        foreach (self::FORM_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $patch[$field] = $data[$field];
            }
        }
        $stored = $enrolid > 0 ? self::load($enrolid) : null;
        if ($stored) {
            foreach (['availablefrom', 'availableuntil'] as $field) {
                $old = (int) $stored->$field;
                $new = isset($patch[$field]) && is_numeric($patch[$field]) ? (int) $patch[$field] : null;
                if ($old > 0 && $new !== null && $new > 0 && self::same_local_minute($old, $new)) {
                    $patch[$field] = $old;
                }
            }
        }
        return $patch;
    }

    /**
     * Write a backed-up configuration row onto a restored instance (full replace, hashes kept as is).
     *
     * Invalid contract values in an old backup fall back to the database default instead of failing
     * the course restore.
     *
     * @param int $enrolid Core enrol instance id of the restored method.
     * @param \stdClass $row Backed-up row with role and group ids already mapped to the target.
     * @return void
     */
    public static function restore_row(int $enrolid, \stdClass $row): void {
        $patch = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $patch[$field] = property_exists($row, $field) ? $row->$field : self::DEFAULTS[$field];
            try {
                self::normalise($field, $patch[$field]);
            } catch (\invalid_parameter_exception $e) {
                $patch[$field] = self::DEFAULTS[$field];
            }
        }
        if (!access_window::is_valid_range((int) $patch['availablefrom'], (int) $patch['availableuntil'])) {
            $patch['availableuntil'] = 0;
        }
        $changes = self::changes($enrolid, $patch);
        foreach (self::RESTORE_COLUMNS as $column) {
            if (property_exists($row, $column)) {
                $changes[$column] = $row->$column;
            }
        }
        self::write($enrolid, $changes, true);
    }

    /**
     * Compute the changed columns a patch produces; writes nothing.
     *
     * @param int $enrolid Core enrol instance id (0 for a new instance).
     * @param array $data Patch.
     * @return array Changed columns => new values (empty when nothing changes).
     * @throws \invalid_parameter_exception When a value or the merged access window is invalid.
     */
    private static function changes(int $enrolid, array $data): array {
        $stored = $enrolid > 0 ? self::load($enrolid) : null;
        $current = [];
        foreach (self::DEFAULTS as $field => $default) {
            $current[$field] = $stored ? self::stored_value($field, $stored->$field) : $default;
        }
        $merged = $current;
        foreach (array_keys(self::FIELDS) as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }
            $value = self::normalise($field, $data[$field]);
            if ($value !== null) {
                $merged[$field] = $value;
            }
        }
        if (!access_window::is_valid_range($merged['availablefrom'], $merged['availableuntil'])) {
            throw new \invalid_parameter_exception('availableuntil: the window must end after it starts');
        }
        $changes = [];
        foreach ($merged as $field => $value) {
            if (!$stored || $value !== $current[$field]) {
                $changes[$field] = $value;
            }
        }
        foreach (self::SECRETS as $input => [$column, $modefield, $mode]) {
            $secret = trim((string) ($data[$input] ?? ''));
            if ($secret !== '' && $merged[$modefield] === $mode) {
                $changes[$column] = password_hash($secret, PASSWORD_DEFAULT);
            }
        }
        return $changes;
    }

    /**
     * Validate and normalise one contract value.
     *
     * @param string $field Contract field.
     * @param mixed $value Raw value.
     * @return int|string|null Normalised value, or null for "keep" (a lifetime of 0).
     * @throws \invalid_parameter_exception When the value is invalid.
     */
    private static function normalise(string $field, $value) {
        $kind = self::FIELDS[$field];
        if (is_array($kind)) {
            if (!is_string($value) || !in_array($value, $kind, true)) {
                throw new \invalid_parameter_exception($field . ': unsupported value');
            }
            return $value;
        }
        if ($kind === 'text') {
            if (!is_scalar($value)) {
                throw new \invalid_parameter_exception($field . ': text expected');
            }
            return trim((string) $value);
        }
        if ($kind === 'flag') {
            return !empty($value) ? 1 : 0;
        }
        if (is_bool($value) || !is_numeric($value) || (int) $value != $value || (int) $value < 0) {
            throw new \invalid_parameter_exception($field . ': non-negative whole number expected');
        }
        $value = (int) $value;
        // A temporary lifetime of 0 is not a usable lifetime: it keeps the stored value.
        return ($kind === 'lifetime' && $value === 0) ? null : $value;
    }

    /**
     * Type a stored column value like a normalised one.
     *
     * @param string $field Contract field.
     * @param mixed $value Stored value.
     * @return int|string
     */
    private static function stored_value(string $field, $value) {
        $kind = self::FIELDS[$field];
        return (is_array($kind) || $kind === 'text') ? (string) $value : (int) $value;
    }

    /**
     * Persist changed columns atomically and refresh the dependent state.
     *
     * @param int $enrolid Core enrol instance id.
     * @param array $changes Columns => values.
     * @param bool $force Write even without changes (restore).
     * @return bool Whether anything was written.
     */
    private static function write(int $enrolid, array $changes, bool $force = false): bool {
        global $DB;
        $existing = self::load($enrolid);
        if ($existing && !$changes && !$force) {
            return false;
        }
        $now = \core\di::get(\core\clock::class)->time();
        $transaction = $DB->start_delegated_transaction();
        if ($existing) {
            $record = (object) ($changes + ['id' => $existing->id, 'timemodified' => $now]);
            $DB->update_record(self::TABLE, $record);
        } else {
            $record = (object) ($changes + self::DEFAULTS + ['enrolid' => $enrolid, 'timemodified' => $now]);
            $DB->insert_record(self::TABLE, $record);
        }
        $period = (int) $DB->get_field(self::TABLE, 'enrolperiod', ['enrolid' => $enrolid]);
        $select = 'id = :id AND (enrolperiod IS NULL OR enrolperiod <> :period)';
        $DB->set_field_select('enrol', 'enrolperiod', $period, $select, ['id' => $enrolid, 'period' => $period]);
        $transaction->allow_commit();

        // A change made in this request must be visible to a later resolution in the same request:
        // purge first, then derive the participant-list override from the fresh policy.
        $courseid = (int) $DB->get_field('enrol', 'courseid', ['id' => $enrolid]);
        if ($courseid) {
            policy_assembler::purge_cache($courseid);
            $policy = \enrol_flexaccess\api::get_effective_policy($courseid);
            participant_list_access::sync($courseid, $policy->participantlistaccess);
        }
        return true;
    }

    /**
     * Whether two times show as the same minute in the date selector (user time zone and calendar).
     *
     * @param int $a Timestamp.
     * @param int $b Timestamp.
     * @return bool
     */
    private static function same_local_minute(int $a, int $b): bool {
        $calendar = \core_calendar\type_factory::get_calendar_instance();
        $da = $calendar->timestamp_to_date_array($a, 99);
        $db = $calendar->timestamp_to_date_array($b, 99);
        foreach (['year', 'mon', 'mday', 'hours', 'minutes'] as $part) {
            if ((int) $da[$part] !== (int) $db[$part]) {
                return false;
            }
        }
        return true;
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
