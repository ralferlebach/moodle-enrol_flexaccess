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
 * Enforces participant-list visibility for FlexAccess visitors.
 *
 * When a course denies participant-list access to its FlexAccess visitors, the roster-viewing
 * capabilities are prevented for the FlexAccess role, so those visitors cannot open the participant
 * list. (This controls the visitor's ACCESS to the list; it does not hide the visitor FROM the list
 * shown to others, for which Moodle offers no stable extension point.) The roster-exposing capabilities are prevented
 * on the dedicated participant role within that course context. Because only FlexAccess visitors
 * hold that role, the override hides the participant list from them without affecting anyone else.
 * The core participant page gate ({@see course_can_view_participants()}) accepts either
 * viewparticipants or enrolreview, so both are prevented.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class participant_list_access {
    /**
     * Capabilities that expose the participant roster; both gate the core participants page.
     */
    private const ROSTER_CAPS = ['moodle/course:viewparticipants', 'moodle/course:enrolreview'];

    /**
     * Synchronise the course-context override on the dedicated role to match the effective visibility.
     *
     * @param int $courseid Course id.
     * @param string $visibility Effective visibility, 'show' or 'hide'.
     * @return void
     */
    public static function sync(int $courseid, string $visibility): void {
        $roleid = participant_role::get_id();
        if ($roleid === 0) {
            return;
        }
        $context = \context_course::instance($courseid);
        foreach (self::ROSTER_CAPS as $cap) {
            if ($visibility === 'hide') {
                assign_capability($cap, CAP_PREVENT, $roleid, $context->id, true);
            } else {
                unassign_capability($cap, $roleid, $context->id);
            }
        }
        $context->mark_dirty();
    }

    /**
     * Re-apply the effective participant-list visibility to every course that has a FlexAccess
     * enrol instance. Called when a higher-level policy changes (system default or widening), since
     * those changes must reach existing instances without needing each one to be re-saved.
     *
     * @return void
     */
    public static function resync_all(): void {
        global $DB;
        $courseids = $DB->get_fieldset_select('enrol', 'DISTINCT courseid', 'enrol = :e', ['e' => 'flexaccess']);
        foreach ($courseids as $courseid) {
            $courseid = (int) $courseid;
            policy_assembler::purge_cache($courseid);
            $policy = \enrol_flexaccess\api::get_effective_policy($courseid);
            self::sync($courseid, $policy->participantlistaccess);
        }
    }
}
