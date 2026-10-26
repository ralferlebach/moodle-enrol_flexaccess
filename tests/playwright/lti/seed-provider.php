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
 * Seed of the LTI provider (tool) site for the two-site LTI 1.3 suite, step 1 of 3.
 *
 * Creates the provider course with a FlexAccess enrolment method and two published LTI 1.3 resources
 * (an assignment): one asking for an existing account (PROMPT_EXISTING_ONLY) and one creating accounts
 * automatically (AUTO_ONLY). Creates a draft platform registration whose final values are filled in by
 * seed-registration.php once the platform exists. Prints shell "export" lines.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once(__DIR__ . '/seedlib.php');

use enrol_lti\local\ltiadvantage\entity\application_registration;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;

\core\session\manager::set_user(get_admin());
flexaccess_lti_seed_site_settings(['lti']);
set_config('enrol_plugins_enabled', 'manual,guest,self,cohort,flexaccess,lti');
\core\plugininfo\auth::enable_plugin('lti', 1);

if ($DB->record_exists('course', ['shortname' => 'PROVIDER'])) {
    cli_error('Already seeded: run on a fresh site.');
}
$course = create_course((object) [
    'fullname' => 'Provider course',
    'shortname' => 'PROVIDER',
    'category' => \core_course_category::get_default()->id,
    'visible' => 1,
    'enablecompletion' => 1,
]);
$plugin = enrol_get_plugin('flexaccess');
$flexenrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
// Temporary access on the provider, so a temporary session can exist before the first LTI binding.
\enrol_flexaccess\local\instance_config::save($flexenrolid, ['allowtemporary' => 1, 'allowquick' => 1]);
\cache::make('enrol_flexaccess', 'policy')->purge();

// The published activity: an assignment whose grade is returned to the platform through AGS.
$assign = create_module((object) [
    'modulename' => 'assign',
    'course' => $course->id,
    'section' => 0,
    'name' => 'Provider assignment',
    'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
    'grade' => 100,
    'visible' => 1,
    'assignsubmission_onlinetext_enabled' => 1,
    'submissiondrafts' => 0,
    'sendnotifications' => 0,
    'sendlatenotifications' => 0,
    'duedate' => 0,
    'allowsubmissionsfromdate' => 0,
    'cutoffdate' => 0,
    'gradingduedate' => 0,
    'teamsubmission' => 0,
    'requireallteammemberssubmit' => 0,
    'blindmarking' => 0,
    'markingworkflow' => 0,
    'requiresubmissionstatement' => 0,
    // Manual completion, so that a journey decides when the activity is complete (gradesynccompletion).
    'completion' => COMPLETION_TRACKING_MANUAL,
]);
// The course is complete when the assignment is (gradesynccompletion of the course resource).
require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');
$DB->insert_record('course_completion_criteria', (object) [
    'course' => $course->id,
    'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
    'module' => 'assign',
    'moduleinstance' => $assign->coursemodule,
]);
$DB->insert_record('course_completion_aggr_methd', (object) [
    'course' => $course->id, 'criteriatype' => null, 'method' => COMPLETION_AGGREGATION_ALL, 'value' => null,
]);

$roles = $DB->get_records_menu('role', null, '', 'shortname, id');
$publish = static function (
    string $name,
    int $mode,
    bool $wholecourse = false,
    int $oncompletion = 0
) use (
    $course,
    $assign,
    $roles
): stdClass {
    global $DB;
    $instanceid = enrol_get_plugin('lti')->add_instance($course, [
        'name' => $name,
        'ltiversion' => 'LTI-1p3',
        // An activity resource (the assignment) or a course resource (the course grade).
        'contextid' => $wholecourse
            ? context_course::instance($course->id)->id
            : context_module::instance($assign->coursemodule)->id,
        'status' => ENROL_INSTANCE_ENABLED,
        'roleinstructor' => $roles['editingteacher'],
        'rolelearner' => $roles['student'],
        'gradesync' => 1,
        'gradesynccompletion' => $oncompletion,
        'membersync' => 1,
        'membersyncmode' => 1,
        'provisioningmodelearner' => $mode,
        'provisioningmodeinstructor' => \auth_plugin_lti::PROVISIONING_MODE_PROMPT_NEW_EXISTING,
        'maxenrolled' => 0,
        'enrolperiod' => 0,
        'enrolstartdate' => 0,
        'enrolenddate' => 0,
        'institution' => '',
        'lang' => 'en',
        'timezone' => '99',
        'maildisplay' => 2,
        'city' => '',
        'country' => '',
    ]);
    return $DB->get_record('enrol_lti_tools', ['enrolid' => $instanceid], '*', MUST_EXIST);
};
require_once($CFG->dirroot . '/auth/lti/auth.php');
$prompt = $publish('Prompt for existing account', \auth_plugin_lti::PROVISIONING_MODE_PROMPT_EXISTING_ONLY);
$auto = $publish('Automatic accounts', \auth_plugin_lti::PROVISIONING_MODE_AUTO_ONLY);
// The grade-return matrix (enrol_flexaccess #10 AK 11, DoD 12): activity and course resources, with and
// without waiting for completion. The two resources above are activity resources without waiting.
$actcompl = $publish('Activity, grade on completion', \auth_plugin_lti::PROVISIONING_MODE_AUTO_ONLY, false, 1);
$wholecourse = $publish('Course', \auth_plugin_lti::PROVISIONING_MODE_AUTO_ONLY, true, 0);
$coursecompl = $publish('Course, grade on completion', \auth_plugin_lti::PROVISIONING_MODE_AUTO_ONLY, true, 1);

// Permanent FlexAccess accounts of people who also learn on the platform (one per journey).
$people = [];
foreach (['robin' => 'Robin', 'alex' => 'Alex', 'sam' => 'Sam', 'pat' => 'Pat'] as $key => $first) {
    $email = $key . '@example.org';
    $people[$key] = flexaccess_lti_seed_durable_user($key . '.provider', $first, 'Provider', $email, 'Provider-Pass!1');
    \enrol_flexaccess\local\enrol_service::admin_enrol((int) $course->id, $people[$key]);
}
// Pat has not set a password yet (converted by an administrator): not a completed persistent login.
$pending = \auth_flexaccess\local\account_state::PENDING_CREDENTIAL;
$DB->set_field('auth_flexaccess_account', 'accountstate', $pending, ['userid' => $people['pat']]);
$durable = $people['robin'];

// Draft registration: the platform values are added by seed-registration.php.
$uniqueid = 'flexaccess-platform';
$repository = new application_registration_repository();
if (!$registration = $repository->find_by_uniqueid($uniqueid)) {
    $registration = $repository->save(application_registration::create_draft('FlexAccess test platform', $uniqueid));
}
// A second registration of the same platform: its second tool deployment (enrol_flexaccess #10 DoD 13).
$uniqueid2 = 'flexaccess-platform-2';
if (!$repository->find_by_uniqueid($uniqueid2)) {
    $repository->save(application_registration::create_draft('FlexAccess test platform, second deployment', $uniqueid2));
}

flexaccess_lti_seed_export([
    'LTI_PROVIDER_COURSE_ID' => $course->id,
    'LTI_PROVIDER_ASSIGN_CMID' => $assign->coursemodule,
    'LTI_PROVIDER_ASSIGN_ID' => $assign->instance,
    'LTI_PROVIDER_PROMPT_UUID' => $prompt->uuid,
    'LTI_PROVIDER_AUTO_UUID' => $auto->uuid,
    'LTI_PROVIDER_PROMPT_TOOLID' => $prompt->id,
    'LTI_PROVIDER_AUTO_TOOLID' => $auto->id,
    'LTI_PROVIDER_ACTCOMPL_UUID' => $actcompl->uuid,
    'LTI_PROVIDER_ACTCOMPL_TOOLID' => $actcompl->id,
    'LTI_PROVIDER_COURSE_TOOLID' => $wholecourse->id,
    'LTI_PROVIDER_COURSECOMPL_TOOLID' => $coursecompl->id,
    'LTI_PROVIDER_COURSE_UUID' => $wholecourse->uuid,
    'LTI_PROVIDER_COURSECOMPL_UUID' => $coursecompl->uuid,
    'LTI_PROVIDER_REGISTRATION_2' => $uniqueid2,
    'LTI_PROVIDER_LOGIN_URL_2' => (new moodle_url('/enrol/lti/login.php', ['id' => $uniqueid2]))->out(false),
    'LTI_PROVIDER_REGISTRATION' => $uniqueid,
    'LTI_PROVIDER_LOGIN_URL' => (new moodle_url('/enrol/lti/login.php', ['id' => $uniqueid]))->out(false),
    'LTI_PROVIDER_LAUNCH_URL' => (new moodle_url('/enrol/lti/launch.php'))->out(false),
    'LTI_PROVIDER_JWKS_URL' => (new moodle_url('/enrol/lti/jwks.php'))->out(false),
    'LTI_PROVIDER_DURABLE_USER' => 'robin.provider',
    'LTI_PROVIDER_DURABLE_PASS' => 'Provider-Pass!1',
    'LTI_PROVIDER_DURABLE_ID' => $durable,
    'LTI_PROVIDER_ALEX_ID' => $people['alex'],
    'LTI_PROVIDER_SAM_ID' => $people['sam'],
    'LTI_PROVIDER_PAT_ID' => $people['pat'],
]);
