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
 * Since 2026102502 FlexAccess assigns its course role with the component enrol_flexaccess and the instance id
 * (protected roles, enrol_flexaccess #15), so its assignments are provably its own. This hook removes exactly
 * those - before core's clean-up, while the enrolments still exist - and never an assignment without that
 * component: a participant role an administrator assigned by hand, in the same course or anywhere else, stays.
 * Assignments without component from releases before 2026102502 that the upgrade could not attribute to
 * FlexAccess (the person had another enrolment in the course) are kept for the same reason. The participant
 * role itself is deleted only when no assignment of it remains; the restriction role is internal to
 * FlexAccess and always deleted.
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
        // Only the assignments FlexAccess made (component enrol_flexaccess), in any context.
        $rs = $DB->get_recordset(
            'role_assignments',
            ['roleid' => $participant, 'component' => 'enrol_flexaccess'],
            'id',
            'id, userid, contextid, itemid'
        );
        foreach ($rs as $ra) {
            role_unassign($participant, (int) $ra->userid, (int) $ra->contextid, 'enrol_flexaccess', (int) $ra->itemid);
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
