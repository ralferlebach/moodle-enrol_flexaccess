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
 * Read-only readiness diagnostics for the parts of FlexAccess that enrol_flexaccess owns.
 *
 * Covers the role model (participant and restriction role), policy conflicts where a course
 * configures a method that a higher level neutralises, and a compact per-course readiness verdict.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class readiness {
    /** Capabilities the restriction role must prohibit. */
    public const RESTRICTED_CAPS = [
        'moodle/site:sendmessage',
        'moodle/user:editownprofile',
        'moodle/user:editownmessageprofile',
    ];

    /** Access-method flags whose course value can be neutralised from above. */
    private const FLAGS = ['allowtemporary', 'allowquick', 'allowguest', 'allownormallogin', 'allowmagiclogin'];

    /**
     * Check the role model.
     *
     * @return string[] Problem codes (empty = fine): participantmissing, participantcontext,
     *     participantarchetype, participantsystemassigned, restrictionmissing, restrictioncontext,
     *     restrictionarchetype, restrictionpositive, restrictionprohibit.
     */
    public static function role_model_problems(): array {
        global $DB;
        $problems = [];
        $system = \context_system::instance();

        $participant = $DB->get_record('role', ['shortname' => participant_role::SHORTNAME]);
        if (!$participant) {
            $problems[] = 'participantmissing';
        } else {
            if (array_values(array_map('intval', get_role_contextlevels((int) $participant->id))) !== [CONTEXT_COURSE]) {
                $problems[] = 'participantcontext';
            }
            if ($participant->archetype !== 'student') {
                $problems[] = 'participantarchetype';
            }
            if ($DB->record_exists('role_assignments', ['roleid' => $participant->id, 'contextid' => $system->id])) {
                $problems[] = 'participantsystemassigned';
            }
        }

        $restriction = $DB->get_record('role', ['shortname' => participant_role::RESTRICTION_SHORTNAME]);
        if (!$restriction) {
            $problems[] = 'restrictionmissing';
            return $problems;
        }
        if (array_values(array_map('intval', get_role_contextlevels((int) $restriction->id))) !== [CONTEXT_SYSTEM]) {
            $problems[] = 'restrictioncontext';
        }
        if ((string) $restriction->archetype !== '') {
            $problems[] = 'restrictionarchetype';
        }
        if (
            $DB->record_exists_select(
                'role_capabilities',
                'roleid = :roleid AND permission > 0',
                ['roleid' => $restriction->id]
            )
        ) {
            $problems[] = 'restrictionpositive';
        }
        foreach (self::RESTRICTED_CAPS as $cap) {
            if (!get_capability_info($cap)) {
                continue;
            }
            $permission = $DB->get_field('role_capabilities', 'permission', [
                'roleid' => $restriction->id,
                'contextid' => $system->id,
                'capability' => $cap,
            ]);
            if ((int) $permission !== CAP_PROHIBIT) {
                $problems[] = 'restrictionprohibit';
                break;
            }
        }
        return $problems;
    }

    /**
     * Recreate or repair both FlexAccess roles (idempotent, deterministic).
     *
     * @return void
     */
    public static function repair_role_model(): void {
        participant_role::ensure();
    }

    /**
     * Course instances that enable a method a higher level switches off.
     *
     * @param int|null $courseid Optional single course.
     * @param int $limit Maximum number of conflicts.
     * @return \stdClass[] Each with courseid, enrolid, flag, cause ('ceiling'|'magicmaster').
     */
    public static function policy_conflicts(?int $courseid = null, int $limit = 200): array {
        global $DB;
        $params = ['enrol' => 'flexaccess', 'status' => ENROL_INSTANCE_ENABLED];
        if ($courseid !== null) {
            $params['courseid'] = $courseid;
        }
        $instances = $DB->get_records('enrol', $params, 'courseid ASC, id ASC', 'id, courseid');
        $magicmaster = !class_exists('\auth_flexaccess\api') || \auth_flexaccess\api::magic_login_enabled();
        $widening = policy_assembler::allow_widening();
        $conflicts = [];
        foreach ($instances as $instance) {
            $config = instance_config::load((int) $instance->id);
            if (!$config) {
                continue;
            }
            $ceiling = policy_assembler::ceiling((int) $instance->courseid);
            foreach (self::FLAGS as $flag) {
                if (empty($config->$flag)) {
                    continue;
                }
                if (!$widening && !$ceiling->$flag) {
                    $conflicts[] = self::conflict($instance, $flag, 'ceiling');
                } else if ($flag === 'allowmagiclogin' && !$magicmaster) {
                    $conflicts[] = self::conflict($instance, $flag, 'magicmaster');
                }
                if (count($conflicts) >= $limit) {
                    return $conflicts;
                }
            }
        }
        return $conflicts;
    }

    /**
     * Compact readiness verdict for one course.
     *
     * @param int $courseid Course id.
     * @return string[] Problem codes (empty = ready): authdisabled, enroldisabled, rolemodel,
     *     policy_<flag>_<cause>.
     */
    public static function course_problems(int $courseid): array {
        $problems = [];
        if (!is_enabled_auth('flexaccess')) {
            $problems[] = 'authdisabled';
        }
        if (!enrol_is_enabled('flexaccess')) {
            $problems[] = 'enroldisabled';
        }
        if (self::role_model_problems()) {
            $problems[] = 'rolemodel';
        }
        foreach (self::policy_conflicts($courseid) as $conflict) {
            $problems[] = 'policy_' . $conflict->flag . '_' . $conflict->cause;
        }
        return array_values(array_unique($problems));
    }

    /**
     * Build a conflict record.
     *
     * @param \stdClass $instance Enrol instance (id, courseid).
     * @param string $flag Policy flag.
     * @param string $cause Cause code.
     * @return \stdClass
     */
    private static function conflict(\stdClass $instance, string $flag, string $cause): \stdClass {
        return (object) [
            'courseid' => (int) $instance->courseid,
            'enrolid' => (int) $instance->id,
            'flag' => $flag,
            'cause' => $cause,
        ];
    }
}
