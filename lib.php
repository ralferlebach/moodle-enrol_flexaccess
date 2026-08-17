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
 * Enrolment plugin class for FlexAccess.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** FlexAccess enrolment plugin. */
class enrol_flexaccess_plugin extends enrol_plugin {
    /** @param stdClass $instance Enrolment instance. @return string */
    public function get_instance_name($instance): string {
        if (empty($instance->name)) {
            return get_string('pluginname', 'enrol_flexaccess');
        }
        return format_string($instance->name);
    }

    /** @param int $courseid Course ID. @return bool */
    public function can_add_instance($courseid): bool {
        return has_capability('moodle/course:enrolconfig', context_course::instance($courseid))
            && has_capability('enrol/flexaccess:config', context_course::instance($courseid));
    }

    /** @return bool */
    public function roles_protected(): bool {
        return false;
    }

    /** @param stdClass $instance Enrolment instance. @param stdClass $ue User enrolment. @return bool */
    public function allow_unenrol(stdClass $instance, stdClass $ue): bool {
        return has_capability('enrol/flexaccess:unenrol', context_course::instance($instance->courseid));
    }
}
