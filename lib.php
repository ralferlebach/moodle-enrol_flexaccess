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
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


use enrol_flexaccess\local\access_window;
use enrol_flexaccess\local\instance_config;

/**
 * FlexAccess enrolment plugin.
 *
 * @package    enrol_flexaccess
 */
class enrol_flexaccess_plugin extends enrol_plugin {
    /**
     * Return the display name of an enrolment instance.
     *
     * @param stdClass $instance Enrolment instance.
     * @return string
     */
    public function get_instance_name($instance): string {
        if (empty($instance->name)) {
            return get_string('pluginname', 'enrol_flexaccess');
        }
        return format_string($instance->name);
    }

    /**
     * Whether an instance may be added to the course.
     *
     * @param int $courseid Course ID.
     * @return bool
     */
    public function can_add_instance($courseid): bool {
        global $DB;
        $context = context_course::instance($courseid);
        if (
            !has_capability('moodle/course:enrolconfig', $context)
                || !has_capability('enrol/flexaccess:config', $context)
        ) {
            return false;
        }
        // Exactly one FlexAccess enrolment method per course keeps policy and capacity deterministic.
        return !$DB->record_exists('enrol', ['enrol' => 'flexaccess', 'courseid' => $courseid]);
    }

    /**
     * Whether assigned roles are protected.
     *
     * @return bool
     */
    public function roles_protected(): bool {
        return false;
    }

    /**
     * Whether unenrolment is allowed for the instance.
     *
     * @param stdClass $instance Enrolment instance.
     * @return bool
     */
    public function allow_unenrol(stdClass $instance): bool {
        return has_capability('enrol/flexaccess:unenrol', context_course::instance($instance->courseid));
    }

    /**
     * Whether a specific user enrolment may be unenrolled.
     *
     * @param stdClass $instance Enrolment instance.
     * @param stdClass $ue User enrolment.
     * @return bool
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue): bool {
        return has_capability('enrol/flexaccess:unenrol', context_course::instance($instance->courseid));
    }

    /**
     * Use the core standard add/edit instance pages.
     *
     * @return bool
     */
    public function use_standard_editing_ui(): bool {
        return true;
    }

    /**
     * Whether this instance can be edited by the current user.
     *
     * @param stdClass $instance Enrolment instance.
     * @return bool
     */
    public function can_edit_instance($instance): bool {
        return has_capability('enrol/flexaccess:config', context_course::instance($instance->courseid));
    }

    /**
     * Default values for the add-instance form.
     *
     * @return array
     */
    public function get_instance_defaults(): array {
        return [
            'status' => ENROL_INSTANCE_ENABLED,
            'availablefrom' => 0,
            'availableuntil' => 0,
            'maxparticipants' => 0,
        ];
    }

    /**
     * Add the instance configuration form elements.
     *
     * This iteration exposes the access window and participant capacity; other extended
     * configuration keeps its stored/default values until later iterations expose it.
     *
     * @param stdClass $instance Enrolment instance (or defaults object for a new instance).
     * @param MoodleQuickForm $mform The form being built.
     * @param context $context Course context.
     * @return void
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context): void {
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $options = [
            ENROL_INSTANCE_ENABLED => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'),
        ];
        $mform->addElement('select', 'status', get_string('status', 'enrol_flexaccess'), $options);

        $mform->addElement('header', 'flexaccess_access', get_string('settings:access', 'enrol_flexaccess'));

        $mform->addElement(
            'date_time_selector',
            'availablefrom',
            get_string('availablefrom', 'enrol_flexaccess'),
            ['optional' => true]
        );
        $mform->addHelpButton('availablefrom', 'availablefrom', 'enrol_flexaccess');

        $mform->addElement(
            'date_time_selector',
            'availableuntil',
            get_string('availableuntil', 'enrol_flexaccess'),
            ['optional' => true]
        );
        $mform->addHelpButton('availableuntil', 'availableuntil', 'enrol_flexaccess');

        $mform->addElement('text', 'maxparticipants', get_string('maxparticipants', 'enrol_flexaccess'));
        $mform->setType('maxparticipants', PARAM_INT);
        $mform->addHelpButton('maxparticipants', 'maxparticipants', 'enrol_flexaccess');
        $mform->setDefault('maxparticipants', 0);

        // Access methods offered by this instance.
        $mform->addElement('header', 'flexaccess_methods', get_string('settings:methods', 'enrol_flexaccess'));

        foreach (['allowtemporary', 'allowquick', 'allowguest', 'allownormallogin'] as $flag) {
            $mform->addElement('advcheckbox', $flag, get_string($flag, 'enrol_flexaccess'));
            $mform->addHelpButton($flag, $flag, 'enrol_flexaccess');
        }
        $mform->setDefault('allownormallogin', 1);

        // Lifetimes and expiry behaviour.
        $mform->addElement('header', 'flexaccess_lifecycle', get_string('settings:lifecycle', 'enrol_flexaccess'));

        $mform->addElement('duration', 'temporarylifetime', get_string('temporarylifetime', 'enrol_flexaccess'));
        $mform->addHelpButton('temporarylifetime', 'temporarylifetime', 'enrol_flexaccess');
        $mform->setDefault('temporarylifetime', 6 * HOURSECS);

        $mform->addElement('select', 'expiryaction', get_string('expiryaction', 'enrol_flexaccess'), [
            'suspend' => get_string('expiryaction:suspend', 'enrol_flexaccess'),
            'unenrol' => get_string('expiryaction:unenrol', 'enrol_flexaccess'),
        ]);
        $mform->addHelpButton('expiryaction', 'expiryaction', 'enrol_flexaccess');
        $mform->setDefault('expiryaction', 'suspend');

        // Populate the extended fields from stored configuration when editing an existing instance.
        if (!empty($instance->id)) {
            $config = \enrol_flexaccess\local\instance_config::load((int) $instance->id);
            if ($config) {
                foreach (
                    ['allowtemporary', 'allowquick', 'allowguest', 'allownormallogin',
                        'temporarylifetime', 'expiryaction'] as $field
                ) {
                    if (isset($config->$field)) {
                        $mform->setDefault($field, $config->$field);
                    }
                }
            }
        }
    }

    /**
     * Validate the instance configuration form.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @param stdClass $instance Enrolment instance.
     * @param context $context Course context.
     * @return array Errors keyed by form element.
     */
    public function edit_instance_validation($data, $files, $instance, $context): array {
        $errors = [];
        $from = (int) ($data['availablefrom'] ?? 0);
        $until = (int) ($data['availableuntil'] ?? 0);
        if (!access_window::is_valid_range($from, $until)) {
            $errors['availableuntil'] = get_string('error:windowrange', 'enrol_flexaccess');
        }
        if ((int) ($data['maxparticipants'] ?? 0) < 0) {
            $errors['maxparticipants'] = get_string('error:maxparticipants', 'enrol_flexaccess');
        }
        return $errors;
    }

    /**
     * Add a new instance and persist its extended FlexAccess configuration.
     *
     * @param stdClass $course Course record.
     * @param array|null $fields Instance fields from the form.
     * @return int New enrol instance id.
     */
    public function add_instance($course, ?array $fields = null): int {
        $instanceid = parent::add_instance($course, $fields);
        instance_config::save($instanceid, (array) $fields);
        return $instanceid;
    }

    /**
     * Update an instance and persist its extended FlexAccess configuration.
     *
     * @param stdClass $instance Existing enrol instance.
     * @param stdClass $data Submitted data.
     * @return bool
     */
    public function update_instance($instance, $data): bool {
        instance_config::save((int) $instance->id, (array) $data);
        return parent::update_instance($instance, $data);
    }

    /**
     * Delete an instance and its extended FlexAccess configuration.
     *
     * @param stdClass $instance Enrol instance.
     * @return void
     */
    public function delete_instance($instance): void {
        instance_config::delete((int) $instance->id);
        parent::delete_instance($instance);
    }
}
