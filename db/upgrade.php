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

    if ($oldversion < 2026081901) {
        // Introduce the dedicated FlexAccess participant role, migrate existing FlexAccess
        // enrolments onto it, and apply the participant-list visibility override for every
        // existing instance so the setting takes effect without re-saving each instance.
        // ensure() also (re)applies the system-context assignability and site-wide restrictions.
        \enrol_flexaccess\local\participant_role::ensure();
        \enrol_flexaccess\local\participant_role::migrate_existing();

        $instances = $DB->get_records('enrol', ['enrol' => 'flexaccess'], '', 'id, courseid');
        foreach ($instances as $instance) {
            $policy = \enrol_flexaccess\api::get_effective_policy((int) $instance->courseid);
            \enrol_flexaccess\local\participant_list_access::sync((int) $instance->courseid, $policy->participantlistaccess);
        }

        upgrade_plugin_savepoint(true, 2026081901, 'enrol', 'flexaccess');
    }

    if ($oldversion < 2026081902) {
        // The ensure() helper now also makes the role assignable at system level and applies the
        // site-wide restrictions (messaging, profile editing). Re-run it and restrict existing
        // temporary visitors so the setting takes effect without re-enrolling them.
        \enrol_flexaccess\local\participant_role::ensure();
        $tempusers = $DB->get_fieldset_select(
            'auth_flexaccess_account',
            'userid',
            'accounttype = :type',
            ['type' => \auth_flexaccess\local\account_type::TEMPORARY_USER]
        );
        foreach ($tempusers as $userid) {
            \enrol_flexaccess\local\participant_role::restrict((int) $userid);
        }

        upgrade_plugin_savepoint(true, 2026081902, 'enrol', 'flexaccess');
    }

    if ($oldversion < 2026081903) {
        $table = new xmldb_table('enrol_flexaccess_instance');
        $fields = [
            new xmldb_field(
                'quickreggatemode',
                XMLDB_TYPE_CHAR,
                '16',
                null,
                XMLDB_NOTNULL,
                null,
                'inherit',
                'temporaryaccesskeyhash'
            ),
            new xmldb_field('quickreggatepasswordhash', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'quickreggatemode'),
            new xmldb_field('quickreggatedomains', XMLDB_TYPE_TEXT, null, null, null, null, null, 'quickreggatepasswordhash'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026081903, 'enrol', 'flexaccess');
    }

    if ($oldversion < 2026081910) {
        // Security fix: the participant role was previously assignable (and assigned) at system
        // context, leaking student-archetype ALLOW capabilities site-wide. Restrict it to course
        // context and move existing visitors onto the new prohibit-only restriction role.
        \enrol_flexaccess\local\participant_role::ensure();
        $restrictionid = \enrol_flexaccess\local\participant_role::ensure_restriction();
        $participantid = \enrol_flexaccess\local\participant_role::get_id();
        $systemid = \context_system::instance()->id;

        if ($participantid) {
            // Remove any lingering system-context assignments of the participant role and
            // re-restrict those users through the dedicated restriction role.
            $assignments = $DB->get_records('role_assignments', [
                'roleid' => $participantid,
                'contextid' => $systemid,
                'component' => 'enrol_flexaccess',
            ]);
            foreach ($assignments as $ra) {
                role_unassign($participantid, (int) $ra->userid, $systemid, 'enrol_flexaccess');
                if ($restrictionid && !user_has_role_assignment((int) $ra->userid, $restrictionid, $systemid)) {
                    role_assign($restrictionid, (int) $ra->userid, $systemid, 'enrol_flexaccess');
                }
            }
        }
        \context_system::instance()->mark_dirty();
        upgrade_plugin_savepoint(true, 2026081910, 'enrol', 'flexaccess');
    }

    if ($oldversion < 2026082411) {
        $dbman = $DB->get_manager();
        // Independent email-link (magic) login as a per-instance access method.
        foreach (['enrol_flexaccess_instance' => 1, 'enrol_flexaccess_policy' => -1] as $tablename => $default) {
            $table = new xmldb_table($tablename);
            $length = ($tablename === 'enrol_flexaccess_policy') ? '2' : '1';
            $field = new xmldb_field(
                'allowmagiclogin',
                XMLDB_TYPE_INTEGER,
                $length,
                null,
                XMLDB_NOTNULL,
                null,
                (string) $default,
                'allownormallogin'
            );
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026082411, 'enrol', 'flexaccess');
    }
    if ($oldversion < 2026082428) {
        $dbman = $DB->get_manager();
        // Rename the setting and its columns to say what the feature actually does: it controls
        // whether a temporary visitor may OPEN the participant list, not whether that visitor is
        // hidden from others. The old name invited exactly that misreading.
        foreach (['enrol_flexaccess_instance', 'enrol_flexaccess_policy'] as $tablename) {
            $table = new xmldb_table($tablename);
            $old = new xmldb_field('participantvisibility', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'inherit');
            if ($dbman->field_exists($table, $old)) {
                $dbman->rename_field($table, $old, 'participantlistaccess');
            }
        }
        // Carry the configured value over, then drop the old key so nothing reads a stale setting.
        $existing = get_config('enrol_flexaccess', 'participantvisibilitydefault');
        if ($existing !== false && get_config('enrol_flexaccess', 'participantlistaccessdefault') === false) {
            set_config('participantlistaccessdefault', $existing, 'enrol_flexaccess');
        }
        if ($existing !== false) {
            unset_config('participantvisibilitydefault', 'enrol_flexaccess');
        }
        upgrade_plugin_savepoint(true, 2026082428, 'enrol', 'flexaccess');
    }
    if ($oldversion < 2026101700) {
        // Register the participant role in $CFG->gradebookroles where the site grades students, so that
        // FlexAccess participants appear in the gradebook, completion tracking and LTI grade services.
        \enrol_flexaccess\local\participant_role::sync_gradebook_role();
        upgrade_plugin_savepoint(true, 2026101700, 'enrol', 'flexaccess');
    }
    if ($oldversion < 2026102100) {
        // The edit form shows the enrolment period from the core enrol row (set_data), while FlexAccess
        // works with its own copy. Bring the core row in line so that opening and saving the form cannot
        // replace the effective period (issue #12); instance_config keeps both equal from now on.
        $rows = $DB->get_records_sql(
            "SELECT e.id, fi.enrolperiod
               FROM {enrol} e
               JOIN {enrol_flexaccess_instance} fi ON fi.enrolid = e.id
              WHERE e.enrol = :enrol AND COALESCE(e.enrolperiod, -1) <> fi.enrolperiod",
            ['enrol' => 'flexaccess']
        );
        foreach ($rows as $row) {
            $DB->set_field('enrol', 'enrolperiod', (int) $row->enrolperiod, ['id' => $row->id]);
        }
        upgrade_plugin_savepoint(true, 2026102100, 'enrol', 'flexaccess');
    }

    if ($oldversion < 2026102502) {
        // Provenance of the participant role (enrol_flexaccess #15): releases before 2026102502 assigned it without
        // component, indistinguishable from an administrator's own assignment. Every FlexAccess enrolment gets the
        // assignment with component and instance; the old one without component is removed only where it can only
        // stem from FlexAccess - the person has no other enrolment in the course. Otherwise it is kept (it may be
        // an administrator's), so no right is silently lost.
        $participantid = (int) $DB->get_field('role', 'id', ['shortname' => 'flexaccessparticipant']);
        if ($participantid > 0) {
            $rs = $DB->get_recordset_sql(
                "SELECT ue.userid, e.id AS enrolid, ctx.id AS contextid,
                        (SELECT COUNT(1) FROM {user_enrolments} oue
                           JOIN {enrol} oe ON oe.id = oue.enrolid
                          WHERE oue.userid = ue.userid AND oe.courseid = e.courseid AND oe.enrol <> 'flexaccess') AS others,
                        (SELECT COUNT(1) FROM {role_assignments} ra
                          WHERE ra.userid = ue.userid AND ra.contextid = ctx.id AND ra.roleid = :rid1
                            AND ra.component = '') AS legacy
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'flexaccess'
                   JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :courselevel",
                ['courselevel' => CONTEXT_COURSE, 'rid1' => $participantid]
            );
            foreach ($rs as $row) {
                if ((int) $row->legacy === 0) {
                    continue;
                }
                role_assign($participantid, (int) $row->userid, (int) $row->contextid, 'enrol_flexaccess', (int) $row->enrolid);
                if ((int) $row->others === 0) {
                    role_unassign($participantid, (int) $row->userid, (int) $row->contextid);
                }
            }
            $rs->close();
        }
        upgrade_plugin_savepoint(true, 2026102502, 'enrol', 'flexaccess');
    }
    return true;
}
