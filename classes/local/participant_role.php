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
 * Lifecycle of the dedicated FlexAccess participant role.
 *
 * Temporary and quick-registered visitors are enrolled with this role rather than the raw student
 * role so that participant-list visibility can be enforced per course by overriding capabilities on
 * a role held only by FlexAccess visitors, without affecting regular students. The role mirrors the
 * student archetype, so holders otherwise behave like ordinary students.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class participant_role {
    /**
     * Short name of the dedicated role.
     */
    public const SHORTNAME = 'flexaccessparticipant';

    /**
     * Ensure the dedicated role exists and return its id. Idempotent.
     *
     * @return int Role id.
     */
    public static function ensure(): int {
        global $DB;
        $existing = $DB->get_field('role', 'id', ['shortname' => self::SHORTNAME]);
        if ($existing) {
            return (int) $existing;
        }
        $roleid = create_role(
            get_string('participantrole', 'enrol_flexaccess'),
            self::SHORTNAME,
            get_string('participantrole_desc', 'enrol_flexaccess'),
            'student'
        );
        set_role_contextlevels($roleid, [CONTEXT_COURSE]);
        // Apply the student archetype's default capabilities at system level.
        reset_role_capabilities($roleid);
        return (int) $roleid;
    }

    /**
     * The dedicated role id, or 0 when it does not yet exist.
     *
     * @return int
     */
    public static function get_id(): int {
        global $DB;
        return (int) ($DB->get_field('role', 'id', ['shortname' => self::SHORTNAME]) ?: 0);
    }

    /**
     * Reassign existing FlexAccess enrolment role assignments to the dedicated role.
     *
     * FlexAccess enrolments assign their role with component 'enrol_flexaccess', so those rows can
     * be migrated precisely without touching manual or other-plugin assignments.
     *
     * @return int Number of assignments migrated.
     */
    public static function migrate_existing(): int {
        global $DB;
        $target = self::ensure();
        $rows = $DB->get_records_select(
            'role_assignments',
            'component = :component AND roleid <> :target',
            ['component' => 'enrol_flexaccess', 'target' => $target]
        );
        $migrated = 0;
        foreach ($rows as $ra) {
            role_unassign((int) $ra->roleid, (int) $ra->userid, (int) $ra->contextid, 'enrol_flexaccess', (int) $ra->itemid);
            role_assign($target, (int) $ra->userid, (int) $ra->contextid, 'enrol_flexaccess', (int) $ra->itemid);
            $migrated++;
        }
        return $migrated;
    }
}
