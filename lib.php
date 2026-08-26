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
     * Action icons on the "Enrolment methods" page: a plain-text badge of the active access modes
     * (D4) and, for anyone who may manage or request access lists, a link into the course-scoped
     * access-list manager (D1).
     *
     * @param stdClass $instance Enrol instance.
     * @return array HTML fragments.
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;
        $icons = parent::get_action_icons($instance);
        $courseid = (int) $instance->courseid;

        // D4: which access modes are live, in plain language, right on the methods page.
        $config = \enrol_flexaccess\local\instance_config::load((int) $instance->id);
        $modes = [];
        foreach (['allowtemporary', 'allowquick', 'allowguest', 'allownormallogin', 'allowmagiclogin'] as $flag) {
            if ($config !== null && !empty($config->$flag)) {
                $modes[] = get_string('mode' . $flag, 'enrol_flexaccess');
            }
        }
        $badge = html_writer::span(
            $modes ? implode(', ', $modes) : get_string('modenone', 'enrol_flexaccess'),
            'badge ' . ($modes ? 'badge-info bg-info' : 'badge-secondary bg-secondary'),
            ['title' => get_string('modelabel', 'enrol_flexaccess')]
        );
        $icons[] = html_writer::span($badge, 'flexaccess-modes mr-2');

        // D1: entry point into the access-list manager (create or request), if tool_flexaccess is
        // installed and recent enough to expose can_request(), and the user may manage or request
        // lists here. method_exists guards against an out-of-step sibling version.
        if (
            class_exists(\tool_flexaccess\local\batch::class)
                && method_exists(\tool_flexaccess\local\batch::class, 'can_request')
                && \tool_flexaccess\local\batch::can_request($courseid)
        ) {
            $icons[] = $OUTPUT->action_icon(
                new moodle_url('/admin/tool/flexaccess/coursebatches.php', ['courseid' => $courseid]),
                new pix_icon('i/users', get_string('accesslists', 'enrol_flexaccess'))
            );
        }
        // Entry point into the role/cohort restriction management for this course. The evaluation
        // engine applies site, category and course rules; this is the course-scoped admin UI.
        if (has_capability('enrol/flexaccess:config', \context_course::instance($courseid))) {
            $icons[] = $OUTPUT->action_icon(
                new moodle_url('/enrol/flexaccess/restrictions.php', ['courseid' => $courseid]),
                new pix_icon('i/permissions', get_string('restrictionsmanage', 'enrol_flexaccess'))
            );
        }
        return $icons;
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
     * Whether the given instance can be hidden or shown from the enrolment methods page.
     *
     * @param \stdClass $instance Enrol instance.
     * @return bool
     */
    public function can_hide_show_instance($instance): bool {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/flexaccess:config', $context);
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
     * Exposes the access methods, lifecycle, access-key gate, quick-registration gate, participant
     * visibility, access window and capacity for the instance; unset fields keep their stored values.
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

        $mform->addElement('header', 'flexaccess_access', get_string('settingsaccess', 'enrol_flexaccess'));

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

        $mform->addElement('select', 'participantvisibility', get_string('participantvisibility', 'enrol_flexaccess'), [
            'inherit' => get_string('participantvisibilityinherit', 'enrol_flexaccess'),
            'show' => get_string('show', 'enrol_flexaccess'),
            'hide' => get_string('hide', 'enrol_flexaccess'),
        ]);
        $mform->addHelpButton('participantvisibility', 'participantvisibility', 'enrol_flexaccess');
        $mform->setDefault('participantvisibility', 'inherit');

        // Access methods offered by this instance.
        $mform->addElement('header', 'flexaccess_methods', get_string('settingsmethods', 'enrol_flexaccess'));

        foreach (['allowtemporary', 'allowquick', 'allowguest', 'allownormallogin', 'allowmagiclogin'] as $flag) {
            $mform->addElement('advcheckbox', $flag, get_string($flag, 'enrol_flexaccess'));
            $mform->addHelpButton($flag, $flag, 'enrol_flexaccess');
        }
        $mform->setDefault('allownormallogin', 1);

        // Warn when a method checkbox has no effect because a higher-level policy (system or category)
        // forbids it and widening is disabled: the "narrow-only" resolver would silently override it.
        $courseid = ($context->contextlevel == CONTEXT_COURSE)
            ? (int) $context->instanceid
            : (int) ($instance->courseid ?? 0);
        if ($courseid > 0 && !\enrol_flexaccess\local\policy_assembler::allow_widening()) {
            $ceiling = \enrol_flexaccess\local\policy_assembler::ceiling($courseid);
            $config = \enrol_flexaccess\local\instance_config::load((int) ($instance->id ?? 0));
            $blocked = [];
            foreach (['allowtemporary', 'allowquick', 'allowguest', 'allownormallogin', 'allowmagiclogin'] as $flag) {
                // Only warn about methods this instance actually asks for (saved on) that the higher
                // level forbids - i.e. a real, silent override. Methods the instance leaves off are
                // simply unavailable and need no warning.
                $wanted = $config !== null ? !empty($config->$flag) : ($flag === 'allownormallogin');
                if ($wanted && !$ceiling->$flag) {
                    $blocked[] = get_string($flag, 'enrol_flexaccess');
                }
            }
            if ($blocked) {
                global $OUTPUT;
                $mform->addElement('static', 'flexaccess_methods_neutralised', '', $OUTPUT->notification(
                    get_string('methodneutralised', 'enrol_flexaccess', implode(', ', $blocked)),
                    \core\output\notification::NOTIFY_WARNING,
                    false
                ));
            }
        }

        // Lifetimes and expiry behaviour.
        $mform->addElement('header', 'flexaccess_lifecycle', get_string('settingslifecycle', 'enrol_flexaccess'));

        $mform->addElement('duration', 'temporarylifetime', get_string('temporarylifetime', 'enrol_flexaccess'));
        $mform->addHelpButton('temporarylifetime', 'temporarylifetime', 'enrol_flexaccess');
        $mform->setDefault('temporarylifetime', 6 * HOURSECS);

        $mform->addElement(
            'duration',
            'enrolperiod',
            get_string('enrolperiod', 'enrol_flexaccess'),
            ['optional' => true, 'defaultunit' => DAYSECS]
        );
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_flexaccess');
        $mform->setDefault('enrolperiod', 0);

        $mform->addElement('select', 'expiryaction', get_string('expiryaction', 'enrol_flexaccess'), [
            'suspend' => get_string('expiryactionsuspend', 'enrol_flexaccess'),
            'unenrol' => get_string('expiryactionunenrol', 'enrol_flexaccess'),
        ]);
        $mform->addHelpButton('expiryaction', 'expiryaction', 'enrol_flexaccess');
        $mform->setDefault('expiryaction', 'suspend');

        // Access key gating for temporary entry.
        $mform->addElement('header', 'flexaccess_key', get_string('settingsaccesskeygate', 'enrol_flexaccess'));

        $mform->addElement('select', 'temporaryaccesskeymode', get_string('temporaryaccesskeymode', 'enrol_flexaccess'), [
            'inherit' => get_string('temporaryaccesskeymodeinherit', 'enrol_flexaccess'),
            'course' => get_string('temporaryaccesskeymodecourse', 'enrol_flexaccess'),
        ]);
        $mform->addHelpButton('temporaryaccesskeymode', 'temporaryaccesskeymode', 'enrol_flexaccess');
        $mform->setDefault('temporaryaccesskeymode', 'inherit');

        $mform->addElement('passwordunmask', 'temporaryaccesskey', get_string('temporaryaccesskey', 'enrol_flexaccess'));
        $mform->setType('temporaryaccesskey', PARAM_RAW);
        $mform->addHelpButton('temporaryaccesskey', 'temporaryaccesskey', 'enrol_flexaccess');
        $mform->hideIf('temporaryaccesskey', 'temporaryaccesskeymode', 'neq', 'course');

        $mform->addElement('header', 'flexaccess_quickreggate', get_string('settingsquickreggate', 'enrol_flexaccess'));

        $mform->addElement('select', 'quickreggatemode', get_string('instancequickreggatemode', 'enrol_flexaccess'), [
            'inherit' => get_string('gateinherit', 'enrol_flexaccess'),
            'none' => get_string('gatenone', 'enrol_flexaccess'),
            'password' => get_string('gatepassword', 'enrol_flexaccess'),
            'domain' => get_string('gatedomain', 'enrol_flexaccess'),
        ]);
        $mform->setDefault('quickreggatemode', 'inherit');

        $mform->addElement(
            'passwordunmask',
            'quickreggatepassword',
            get_string('instancequickreggatepassword', 'enrol_flexaccess')
        );
        $mform->setType('quickreggatepassword', PARAM_RAW);
        $mform->hideIf('quickreggatepassword', 'quickreggatemode', 'neq', 'password');

        $mform->addElement(
            'textarea',
            'quickreggatedomains',
            get_string('instancequickreggatedomains', 'enrol_flexaccess')
        );
        $mform->setType('quickreggatedomains', PARAM_RAW);
        $mform->hideIf('quickreggatedomains', 'quickreggatemode', 'neq', 'domain');

        // Populate the extended fields from stored configuration when editing an existing instance.
        if (!empty($instance->id)) {
            $config = \enrol_flexaccess\local\instance_config::load((int) $instance->id);
            if ($config) {
                foreach (
                    ['allowtemporary', 'allowquick', 'allowguest', 'allownormallogin', 'allowmagiclogin',
                        'temporarylifetime', 'enrolperiod', 'expiryaction', 'temporaryaccesskeymode',
                        'participantvisibility', 'quickreggatemode', 'quickreggatedomains'] as $field
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
            $errors['availableuntil'] = get_string('errorwindowrange', 'enrol_flexaccess');
        }
        if ((int) ($data['maxparticipants'] ?? 0) < 0) {
            $errors['maxparticipants'] = get_string('errormaxparticipants', 'enrol_flexaccess');
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
     * Course-side entry on the enrolment page for a logged-in, not-yet-enrolled user.
     *
     * Anonymous entry is handled by the target-aware login-page link (loginpage_idp_list ->
     * access.php). A logged-in real user, however, is redirected straight back out by access.php,
     * so without this hook they hit a dead end on enrol/index.php. When the effective policy offers
     * normal login, this renders a button that enrols them through the capacity-aware service and
     * redirects into the course.
     *
     * @param stdClass $instance Enrol instance.
     * @return string HTML for the enrolment page (empty when nothing is offered).
     */
    public function enrol_page_hook($instance) {
        global $USER, $OUTPUT;

        if (!isloggedin() || isguestuser()) {
            return '';
        }
        $courseid = (int) $instance->courseid;
        $context = context_course::instance($courseid);
        if (is_enrolled($context, $USER, '', true)) {
            return '';
        }
        if (
            (int) $instance->status !== ENROL_INSTANCE_ENABLED
                || !\enrol_flexaccess\api::offers_normal_login($courseid)
        ) {
            return '';
        }

        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
                && optional_param('flexaccessenrol', 0, PARAM_BOOL) && confirm_sesskey()
        ) {
            $status = \enrol_flexaccess\local\enrol_service::enrol_with_capacity((int) $instance->id, (int) $USER->id);
            if ($status === 'enrolled') {
                redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
            }
            $message = $status === 'full'
                ? get_string('coursefull', 'enrol_flexaccess')
                : get_string('accessunavailable', 'auth_flexaccess');
            return $OUTPUT->box($OUTPUT->notification($message, \core\output\notification::NOTIFY_WARNING, false));
        }

        $button = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $button .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'flexaccessenrol', 'value' => 1]);
        $button .= html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('accessenter', 'enrol_flexaccess'),
            'class' => 'btn btn-primary',
        ]);
        $form = html_writer::tag('form', $button, [
            'method' => 'post',
            'action' => (new moodle_url('/enrol/index.php', ['id' => $courseid]))->out(false),
        ]);
        return $OUTPUT->box(html_writer::tag('p', get_string('accessenterintro', 'enrol_flexaccess')) . $form);
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

/**
 * Register plugin status checks (Site administration > Reports > Health).
 *
 * @return \core\check\check[]
 */
function enrol_flexaccess_status_checks(): array {
    return [
        new \enrol_flexaccess\check\coupling(),
    ];
}

/**
 * Re-apply FlexAccess participant-list visibility to all courses with a FlexAccess instance.
 *
 * Registered as the update callback for the system-level visibility default and the widening
 * switch, so changing either immediately reaches existing instances (no per-instance re-save).
 *
 * @return void
 */
function enrol_flexaccess_resync_participant_visibility() {
    \enrol_flexaccess\local\participant_visibility::resync_all();
}
