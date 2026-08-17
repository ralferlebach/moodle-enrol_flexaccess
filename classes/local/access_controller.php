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
 * FlexAccess temporary-access controller.
 *
 * Composes the tested building blocks into the anonymous grant flow: evaluate the effective
 * policy and access window, check capacity, create a temporary user via the auth facade, enrol
 * it under the capacity lock, and schedule a persistence follow-up. Cross-plugin facade calls
 * are made lazily at runtime only (ADR-010).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/** Orchestrates granting temporary access to a course. */
final class access_controller {
    /** Default follow-up delay after account creation, in seconds. */
    private const FOLLOWUP_AFTER = DAYSECS;

    /**
     * Grant temporary access to a course for a new anonymous visitor.
     *
     * @param int $courseid Course id.
     * @param int|null $now Current time.
     * @return \stdClass Result with ->status (granted|closed|notallowed|notenabled|full|<enrolstatus>),
     *                   ->userid and ->enrolid (0 when not applicable).
     */
    public static function grant_temporary_access(int $courseid, ?int $now = null): \stdClass {
        $now = $now ?? time();
        $policy = \enrol_flexaccess\api::get_effective_policy($courseid);

        if (!access_gate::is_flexaccess_open($policy, $now)) {
            return self::result('closed');
        }
        if (!$policy->allowtemporary) {
            return self::result('notallowed');
        }
        $enrolid = self::enabled_instance($courseid);
        if ($enrolid === 0) {
            return self::result('notenabled');
        }
        $active = \enrol_flexaccess\api::get_active_enrolment_count($courseid, $now);
        if (!capacity_service::has_free_capacity($active, $policy->maxparticipants)) {
            return self::result('full');
        }

        $timeexpires = $policy->temporarylifetime > 0 ? $now + $policy->temporarylifetime : null;
        $userid = \auth_flexaccess\api::create_temporary_user($timeexpires, $courseid, null, $now);

        $enrolstatus = enrol_service::enrol_with_capacity($enrolid, $userid, $now);
        if ($enrolstatus !== 'enrolled') {
            return self::result($enrolstatus, $userid, $enrolid);
        }

        \auth_flexaccess\api::request_persistence_followup($userid, self::FOLLOWUP_AFTER);
        return self::result('granted', $userid, $enrolid);
    }

    /**
     * Find the first enabled FlexAccess enrol instance of a course.
     *
     * @param int $courseid Course id.
     * @return int Enrol instance id, or 0 when none is enabled.
     */
    private static function enabled_instance(int $courseid): int {
        global $DB;
        $record = $DB->get_record('enrol', [
            'enrol' => 'flexaccess',
            'courseid' => $courseid,
            'status' => ENROL_INSTANCE_ENABLED,
        ], 'id', IGNORE_MULTIPLE);
        return $record ? (int) $record->id : 0;
    }

    /**
     * Build a result object.
     *
     * @param string $status Result status.
     * @param int $userid Created user id, if any.
     * @param int $enrolid Enrol instance id, if any.
     * @return \stdClass
     */
    private static function result(string $status, int $userid = 0, int $enrolid = 0): \stdClass {
        return (object) ['status' => $status, 'userid' => $userid, 'enrolid' => $enrolid];
    }
}
