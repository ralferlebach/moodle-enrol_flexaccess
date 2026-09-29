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
 * Uninstall hook of enrol_flexaccess: remove what FlexAccess created outside its own tables.
 *
 * Core removes FlexAccess enrolments and role assignments that carry the enrol_flexaccess component.
 * FlexAccess, however, grants its course role through enrol_user() without a component (its roles are
 * not protected), so those assignments would survive the uninstall without any enrolment. This hook
 * runs before core's clean-up, while the enrolments still exist, and removes exactly the course-role
 * assignments that belong to FlexAccess enrolments. It then deletes the two FlexAccess roles - the
 * participant role only if nothing outside FlexAccess still uses it, so an administrator's own use of
 * that role is never silently revoked.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Uninstall clean-up.
 *
 * @return bool
 */
function xmldb_enrol_flexaccess_uninstall() {
    global $DB;
    $participant = (int) $DB->get_field('role', 'id', ['shortname' => 'flexaccessparticipant']);
    $restriction = (int) $DB->get_field('role', 'id', ['shortname' => 'flexaccessrestricted']);

    if ($participant > 0) {
        // Course-role assignments that exist because of a FlexAccess enrolment.
        $rs = $DB->get_recordset_sql(
            "SELECT DISTINCT ue.userid, ctx.id AS contextid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'flexaccess'
               JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :courselevel",
            ['courselevel' => CONTEXT_COURSE]
        );
        foreach ($rs as $row) {
            role_unassign($participant, (int) $row->userid, (int) $row->contextid);
            role_unassign($participant, (int) $row->userid, (int) $row->contextid, 'enrol_flexaccess');
        }
        $rs->close();
        if (!$DB->record_exists('role_assignments', ['roleid' => $participant])) {
            delete_role($participant);
        }
    }
    if ($restriction > 0) {
        // Purely internal to FlexAccess: delete_role() also removes its assignments.
        delete_role($restriction);
    }
    return true;
}
