<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Manage the role and cohort restrictions that apply to FlexAccess in a course.
 *
 * The evaluation engine (restriction_service/restriction_evaluator) already understood system,
 * category and course scoped rules; this page is the administrative entry point for the course
 * scope, so the feature is operable and not just implemented.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use enrol_flexaccess\local\restriction_service;

require_once($CFG->dirroot . '/cohort/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('enrol/flexaccess:config', $context);

$pageurl = new moodle_url('/enrol/flexaccess/restrictions.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('restrictionstitle', 'enrol_flexaccess'));
$PAGE->set_heading(format_string($course->fullname));

if ($action === 'delete') {
    // Deleting a restriction changes who may enter the course: POST only, never a GET link.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidrequest', 'error');
    }
    require_sesskey();
    $id = required_param('id', PARAM_INT);
    // Scope-checked deletion: a course admin must not be able to remove a system or category rule.
    restriction_service::delete($id, 'course', $courseid);
    redirect($pageurl, get_string('restrictionsdeleted', 'enrol_flexaccess'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'add') {
    require_sesskey();
    $kind = required_param('kind', PARAM_ALPHA);
    $effect = required_param('effect', PARAM_ALPHA);
    // The role and cohort selectors are separate fields, so one cannot silently overwrite the other.
    $refid = $kind === restriction_service::KIND_COHORT
        ? optional_param('refidcohort', 0, PARAM_INT)
        : optional_param('refidrole', 0, PARAM_INT);
    $validkinds = [restriction_service::KIND_ROLE, restriction_service::KIND_COHORT];
    $valideffects = [restriction_service::EFFECT_ALLOW, restriction_service::EFFECT_DENY];
    if (!in_array($kind, $validkinds, true) || !in_array($effect, $valideffects, true) || $refid <= 0) {
        redirect($pageurl, get_string('restrictionsinvalid', 'enrol_flexaccess'), null, \core\output\notification::NOTIFY_ERROR);
    }
    restriction_service::add('course', $courseid, $kind, $refid, $effect);
    redirect($pageurl, get_string('restrictionsadded', 'enrol_flexaccess'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Reference data for rendering names and for the add form.
$roles = role_fix_names(get_all_roles($context), $context, ROLENAME_ALIAS, true);
$cohorts = [];
foreach (cohort_get_available_cohorts($context, COHORT_ALL, 0, 0) as $cohort) {
    $cohorts[$cohort->id] = $cohort->name;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('restrictionstitle', 'enrol_flexaccess'));
echo html_writer::tag('p', get_string('restrictionsintro', 'enrol_flexaccess'));

$restrictions = restriction_service::for_scope('course', $courseid);
if ($restrictions) {
    $table = new html_table();
    $table->head = [
        get_string('restrictionskind', 'enrol_flexaccess'),
        get_string('restrictionsreference', 'enrol_flexaccess'),
        get_string('restrictionseffect', 'enrol_flexaccess'),
        get_string('actions'),
    ];
    foreach ($restrictions as $r) {
        if ($r->kind === restriction_service::KIND_ROLE) {
            $name = $roles[$r->refid]->localname ?? get_string('restrictionsmissingref', 'enrol_flexaccess');
        } else {
            $name = $cohorts[$r->refid] ?? get_string('restrictionsmissingref', 'enrol_flexaccess');
        }
        $delete = $OUTPUT->render(new single_button(
            new moodle_url($pageurl, ['action' => 'delete', 'id' => $r->id]),
            get_string('delete'),
            'post'
        ));
        $table->data[] = [
            get_string('restrictionskind' . $r->kind, 'enrol_flexaccess'),
            s($name),
            get_string('restrictionseffect' . $r->effect, 'enrol_flexaccess'),
            $delete,
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('restrictionsnone', 'enrol_flexaccess'), 'info');
}

// Add form. Kept as a plain scoped form (rather than a moodleform) because the reference selector
// depends on the chosen kind and the whole interaction is a single POST.
$rolelist = [];
foreach ($roles as $role) {
    $rolelist[$role->id] = $role->localname;
}

echo $OUTPUT->heading(get_string('restrictionsadd', 'enrol_flexaccess'), 3);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false), 'class' => 'mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add']);
echo html_writer::div(
    html_writer::label(get_string('restrictionskind', 'enrol_flexaccess'), 'fa-kind')
    . html_writer::select(
        [
            restriction_service::KIND_ROLE => get_string('restrictionskindrole', 'enrol_flexaccess'),
            restriction_service::KIND_COHORT => get_string('restrictionskindcohort', 'enrol_flexaccess'),
        ],
        'kind',
        restriction_service::KIND_ROLE,
        false,
        ['id' => 'fa-kind', 'class' => 'form-select']
    ),
    'mb-2'
);
echo html_writer::div(
    html_writer::label(get_string('restrictionsrole', 'enrol_flexaccess'), 'fa-role')
    . html_writer::select($rolelist, 'refidrole', '', false, ['id' => 'fa-role', 'class' => 'form-select']),
    'mb-2'
);
if ($cohorts) {
    echo html_writer::div(
        html_writer::label(get_string('restrictionscohorthint', 'enrol_flexaccess'), 'fa-cohort')
        . html_writer::select($cohorts, 'refidcohort', '', false, ['id' => 'fa-cohort', 'class' => 'form-select']),
        'mb-2'
    );
}
echo html_writer::div(
    html_writer::label(get_string('restrictionseffect', 'enrol_flexaccess'), 'fa-effect')
    . html_writer::select(
        [
            restriction_service::EFFECT_ALLOW => get_string('restrictionseffectallow', 'enrol_flexaccess'),
            restriction_service::EFFECT_DENY => get_string('restrictionseffectdeny', 'enrol_flexaccess'),
        ],
        'effect',
        restriction_service::EFFECT_ALLOW,
        false,
        ['id' => 'fa-effect', 'class' => 'form-select']
    ),
    'mb-2'
);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('add'),
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
