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
 * Builds the effective FlexAccess policy from system, category and instance layers.
 *
 * Precedence is system default -> category policy (ancestry, top-down) -> course enrol
 * instance. Prohibitions are inherited restrictively; a lower layer cannot re-enable a
 * higher-level prohibition unless widening is explicitly allowed. Only default-style values
 * (lifetimes, window, capacity) may be overridden freely.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/** Resolves the effective policy for a course. */
final class policy_assembler {
    /**
     * Read the system-default policy from plugin configuration.
     *
     * @return policy
     */
    public static function system_policy(): policy {
        $p = new policy();
        $vis = get_config('enrol_flexaccess', 'participantvisibilitydefault');
        $p->participantvisibility = in_array($vis, ['show', 'hide'], true) ? $vis : 'show';
        $p->temporaryaccesskeyrequired = (bool) get_config('enrol_flexaccess', 'temporaryaccesskeyrequired');
        return $p;
    }

    /**
     * Whether lower scopes are allowed to widen a higher-level prohibition.
     *
     * @return bool
     */
    public static function allow_widening(): bool {
        return (bool) get_config('enrol_flexaccess', 'allowwidening');
    }

    /**
     * Apply category policy overrides along the course category ancestry (top-down).
     *
     * @param policy $p Policy accumulated so far.
     * @param int $courseid Course id.
     * @param bool $allowwidening Whether a lower scope may widen a prohibition.
     * @return policy
     */
    public static function apply_categories(policy $p, int $courseid, bool $allowwidening): policy {
        global $DB;
        $course = get_course($courseid);
        if (empty($course->category)) {
            return $p;
        }
        $cat = \core_course_category::get($course->category, IGNORE_MISSING, true);
        if (!$cat) {
            return $p;
        }
        $orderedids = array_merge($cat->get_parents(), [$cat->id]); // Root first, leaf last.
        if (!$orderedids) {
            return $p;
        }
        $rows = $DB->get_records_list('enrol_flexaccess_policy', 'categoryid', $orderedids);
        $byid = [];
        foreach ($rows as $row) {
            $byid[(int) $row->categoryid] = $row;
        }
        foreach ($orderedids as $catid) {
            if (!isset($byid[$catid])) {
                continue;
            }
            $p = self::merge_category_row($p, $byid[$catid], $allowwidening);
        }
        return $p;
    }

    /**
     * Merge a single category policy row into the accumulated policy.
     *
     * @param policy $p Accumulated policy.
     * @param \stdClass $row Category policy row (int flags: -1 = inherit).
     * @param bool $allowwidening Whether a lower scope may widen a prohibition.
     * @return policy
     */
    private static function merge_category_row(policy $p, \stdClass $row, bool $allowwidening): policy {
        $p->allowtemporary = policy_resolver::merge_permission(
            $p->allowtemporary, self::flag((int) $row->allowtemporary), $allowwidening);
        $p->allowquick = policy_resolver::merge_permission(
            $p->allowquick, self::flag((int) $row->allowquick), $allowwidening);
        $p->allowguest = policy_resolver::merge_permission(
            $p->allowguest, self::flag((int) $row->allowguest), $allowwidening);
        $p->allownormallogin = policy_resolver::merge_permission(
            $p->allownormallogin, self::flag((int) $row->allownormallogin), $allowwidening);
        if ($row->temporarylifetime !== null) {
            $p->temporarylifetime = (int) $row->temporarylifetime;
        }
        if ($row->provisionallifetime !== null) {
            $p->provisionallifetime = (int) $row->provisionallifetime;
        }
        if (in_array($row->participantvisibility, ['show', 'hide'], true)) {
            $p->participantvisibility = $row->participantvisibility;
        }
        return $p;
    }

    /**
     * Apply the course enrol-instance extension row.
     *
     * @param policy $p Accumulated policy.
     * @param \stdClass $flex Extension row from enrol_flexaccess_instance.
     * @param bool $allowwidening Whether a lower scope may widen a prohibition.
     * @return policy
     */
    public static function apply_instance(policy $p, \stdClass $flex, bool $allowwidening): policy {
        $p->allowtemporary = policy_resolver::merge_permission($p->allowtemporary, (bool) $flex->allowtemporary, $allowwidening);
        $p->allowquick = policy_resolver::merge_permission($p->allowquick, (bool) $flex->allowquick, $allowwidening);
        $p->allowguest = policy_resolver::merge_permission($p->allowguest, (bool) $flex->allowguest, $allowwidening);
        $p->allownormallogin = policy_resolver::merge_permission($p->allownormallogin, (bool) $flex->allownormallogin, $allowwidening);
        $p->temporarylifetime = (int) $flex->temporarylifetime;
        $p->provisionallifetime = (int) $flex->provisionallifetime;
        $p->availablefrom = (int) $flex->availablefrom;
        $p->availableuntil = (int) $flex->availableuntil;
        $p->maxparticipants = (int) $flex->maxparticipants;
        $p->participantvisibility = policy_resolver::participant_visibility(
            in_array($p->participantvisibility, ['show', 'hide'], true) ? $p->participantvisibility : 'show',
            in_array($flex->participantvisibility, ['inherit', 'show', 'hide'], true) ? $flex->participantvisibility : 'inherit');
        $mode = in_array($flex->temporaryaccesskeymode, ['inherit', 'course'], true) ? $flex->temporaryaccesskeymode : 'inherit';
        $p->temporaryaccesskeyscope = policy_resolver::temporary_access_key_scope($p->temporaryaccesskeyrequired, $mode);
        return $p;
    }

    /**
     * Assemble the effective policy for a course.
     *
     * @param int $courseid Course id.
     * @return policy
     */
    public static function assemble(int $courseid): policy {
        global $DB;
        $allowwidening = self::allow_widening();
        $p = self::system_policy();
        $p = self::apply_categories($p, $courseid, $allowwidening);

        $enrol = $DB->get_record('enrol', [
            'enrol' => 'flexaccess',
            'courseid' => $courseid,
            'status' => ENROL_INSTANCE_ENABLED,
        ], '*', IGNORE_MULTIPLE);
        if ($enrol) {
            $flex = instance_config::load((int) $enrol->id);
            if ($flex) {
                $p = self::apply_instance($p, $flex, $allowwidening);
            }
        }
        // Resolve access-key scope even when no instance override is present.
        if ($p->temporaryaccesskeyscope === 'none' && $p->temporaryaccesskeyrequired) {
            $p->temporaryaccesskeyscope = 'system';
        }
        return $p;
    }

    /**
     * Convert a category int flag to a nullable boolean (-1 = inherit).
     *
     * @param int $value Stored flag.
     * @return bool|null
     */
    private static function flag(int $value): ?bool {
        if ($value < 0) {
            return null;
        }
        return $value === 1;
    }
}
