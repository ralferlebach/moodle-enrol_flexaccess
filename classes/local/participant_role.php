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
     * Short name of the dedicated course participant role (student-archetype, course context only).
     */
    public const SHORTNAME = 'flexaccessparticipant';

    /**
     * Short name of the site-wide restriction role (prohibit-only, system context only).
     */
    public const RESTRICTION_SHORTNAME = 'flexaccessrestricted';

    /**
     * Capabilities hard-denied for FlexAccess visitors site-wide (anti-abuse of anonymous accounts).
     */
    private const RESTRICTED_CAPS = [
        'moodle/site:sendmessage',
        'moodle/user:editownprofile',
        'moodle/user:editownmessageprofile',
    ];

    /**
     * Ensure the dedicated course role exists and return its id. Idempotent.
     *
     * This role carries the student-archetype capabilities and is assignable ONLY in course context,
     * so it never grants any positive capability site-wide. The site-wide anti-abuse restrictions live
     * in a separate prohibit-only role (see {@see ensure_restriction()}).
     *
     * @return int Role id.
     */
    public static function ensure(): int {
        global $DB;
        // Always make sure the companion restriction role exists too.
        self::ensure_restriction();
        $existing = $DB->get_field('role', 'id', ['shortname' => self::SHORTNAME]);
        if ($existing) {
            // Repair earlier installs that made this role assignable (and assigned) at system level.
            set_role_contextlevels((int) $existing, [CONTEXT_COURSE]);
            return (int) $existing;
        }
        $roleid = create_role(
            get_string('participantrole', 'enrol_flexaccess'),
            self::SHORTNAME,
            get_string('participantrole_desc', 'enrol_flexaccess'),
            'student'
        );
        // Course context only: the role's capabilities apply where it is assigned (in a course),
        // never site-wide.
        set_role_contextlevels($roleid, [CONTEXT_COURSE]);
        self::apply_archetype_capabilities($roleid);
        return (int) $roleid;
    }

    /**
     * Ensure the site-wide restriction role exists and return its id. Idempotent.
     *
     * This role has no archetype and only the hard-denied capabilities, so assigning it to a visitor
     * at system level withdraws messaging/profile editing site-wide without granting anything.
     *
     * @return int Role id.
     */
    public static function ensure_restriction(): int {
        global $DB;
        $existing = $DB->get_field('role', 'id', ['shortname' => self::RESTRICTION_SHORTNAME]);
        if ($existing) {
            set_role_contextlevels((int) $existing, [CONTEXT_SYSTEM]);
            self::apply_system_restrictions((int) $existing);
            return (int) $existing;
        }
        $roleid = create_role(
            get_string('restrictionrole', 'enrol_flexaccess'),
            self::RESTRICTION_SHORTNAME,
            get_string('restrictionrole_desc', 'enrol_flexaccess'),
            ''
        );
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        self::apply_system_restrictions($roleid);
        return (int) $roleid;
    }

    /**
     * Id of the site-wide restriction role, or 0 when it does not exist yet.
     *
     * @return int
     */
    public static function get_restriction_id(): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => self::RESTRICTION_SHORTNAME]);
    }

    /**
     * Apply the student archetype's default capabilities, skipping any not currently defined.
     *
     * @param int $roleid Role id.
     * @return void
     */
    private static function apply_archetype_capabilities(int $roleid): void {
        $system = \context_system::instance();
        foreach (get_default_capabilities('student') as $capability => $permission) {
            if ((int) $permission === CAP_INHERIT) {
                continue;
            }
            if (get_capability_info($capability)) {
                assign_capability($capability, (int) $permission, $roleid, $system->id, true);
            }
        }
    }

    /**
     * Define the hard-denied capabilities on the restriction role at system level.
     *
     * @param int $roleid Restriction role id.
     * @return void
     */
    private static function apply_system_restrictions(int $roleid): void {
        $system = \context_system::instance();
        foreach (self::RESTRICTED_CAPS as $cap) {
            if (get_capability_info($cap)) {
                // Hard deny: PROHIBIT reliably overrides the authenticated-user ALLOW for these
                // capabilities while the visitor holds this role. It is lifted on conversion.
                assign_capability($cap, CAP_PROHIBIT, $roleid, $system->id, true);
            }
        }
        $system->mark_dirty();
    }

    /**
     * Assign the restriction role to a visitor at system level so the site-wide denials apply.
     *
     * @param int $userid User id.
     * @return void
     */
    public static function restrict(int $userid): void {
        $roleid = self::ensure_restriction();
        if ($roleid === 0) {
            return;
        }
        $system = \context_system::instance();
        if (!user_has_role_assignment($userid, $roleid, $system->id)) {
            role_assign($roleid, $userid, $system->id, 'enrol_flexaccess');
        }
    }

    /**
     * Lift the site-wide restrictions for a user (e.g. once they convert to a full account).
     *
     * @param int $userid User id.
     * @return void
     */
    public static function unrestrict(int $userid): void {
        $roleid = self::get_restriction_id();
        if ($roleid === 0) {
            return;
        }
        role_unassign($roleid, $userid, \context_system::instance()->id, 'enrol_flexaccess');
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
