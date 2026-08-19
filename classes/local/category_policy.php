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

namespace enrol_flexaccess\local;

/**
 * Read/write access to category-level FlexAccess policy overrides.
 *
 * A category row carries tri-state permission flags (-1 = inherit, 1 = allow, 0 = deny), optional
 * lifetimes (null = inherit) and a participant-visibility override (inherit|show|hide). The merge
 * logic that folds these into the effective policy lives in {@see policy_assembler}; this class is
 * the administrative write path that was previously missing.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class category_policy {
    /** Table name. */
    private const TABLE = 'enrol_flexaccess_policy';

    /** Permission flags stored as tri-state ints. */
    private const FLAGS = ['allowtemporary', 'allowquick', 'allowguest', 'allownormallogin'];

    /**
     * Load a category's override row.
     *
     * @param int $categoryid Category id.
     * @return \stdClass|null
     */
    public static function load(int $categoryid): ?\stdClass {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['categoryid' => $categoryid]);
        return $row ?: null;
    }

    /**
     * All override rows keyed by category id.
     *
     * @return \stdClass[]
     */
    public static function all(): array {
        global $DB;
        $result = [];
        foreach ($DB->get_records(self::TABLE) as $row) {
            $result[(int) $row->categoryid] = $row;
        }
        return $result;
    }

    /**
     * Whether a submitted set of values would leave no actual override (everything inherited).
     *
     * @param array $data Normalised field values.
     * @return bool
     */
    private static function is_empty(array $data): bool {
        foreach (self::FLAGS as $flag) {
            if ((int) $data[$flag] !== -1) {
                return false;
            }
        }
        if ($data['temporarylifetime'] !== null || $data['provisionallifetime'] !== null) {
            return false;
        }
        if ($data['participantvisibility'] !== 'inherit') {
            return false;
        }
        return true;
    }

    /**
     * Save (or clear) a category's override row.
     *
     * When every value is set to inherit the row is deleted, so an empty override never lingers.
     *
     * @param int $categoryid Category id.
     * @param array $data Raw submitted data.
     * @return void
     */
    public static function save(int $categoryid, array $data): void {
        global $DB;
        $normalised = [
            'allowtemporary' => self::flag($data['allowtemporary'] ?? -1),
            'allowquick' => self::flag($data['allowquick'] ?? -1),
            'allowguest' => self::flag($data['allowguest'] ?? -1),
            'allownormallogin' => self::flag($data['allownormallogin'] ?? -1),
            'temporarylifetime' => self::nullable_int($data['temporarylifetime'] ?? null),
            'provisionallifetime' => self::nullable_int($data['provisionallifetime'] ?? null),
            'participantvisibility' => in_array($data['participantvisibility'] ?? 'inherit', ['inherit', 'show', 'hide'], true)
                ? $data['participantvisibility'] : 'inherit',
        ];

        if (self::is_empty($normalised)) {
            self::delete($categoryid);
            return;
        }

        $existing = self::load($categoryid);
        $normalised['timemodified'] = time();
        if ($existing) {
            $normalised['id'] = $existing->id;
            $normalised['categoryid'] = $categoryid;
            $DB->update_record(self::TABLE, (object) $normalised);
        } else {
            $normalised['categoryid'] = $categoryid;
            $DB->insert_record(self::TABLE, (object) $normalised);
        }
    }

    /**
     * Delete a category's override row.
     *
     * @param int $categoryid Category id.
     * @return void
     */
    public static function delete(int $categoryid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['categoryid' => $categoryid]);
    }

    /**
     * Clamp a tri-state permission flag to -1|0|1.
     *
     * @param mixed $value Raw value.
     * @return int
     */
    private static function flag($value): int {
        $value = (int) $value;
        if ($value === 1 || $value === 0) {
            return $value;
        }
        return -1;
    }

    /**
     * Normalise a nullable non-negative integer (empty/zero-or-less becomes null = inherit).
     *
     * @param mixed $value Raw value.
     * @return int|null
     */
    private static function nullable_int($value): ?int {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (int) $value;
        return $value > 0 ? $value : null;
    }
}
