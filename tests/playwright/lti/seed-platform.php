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
 * Seed of the LTI platform (consumer) site for the two-site LTI 1.3 suite, step 2 of 3.
 *
 * Expects the provider's values in the environment (from seed-provider.php). Creates an LTI 1.3 tool
 * type pointing at the provider with AGS and NRPS enabled, the platform course with a FlexAccess
 * enrolment method, a permanent FlexAccess learner, and one external-tool activity per published
 * provider resource (opened in a new window). Prints shell "export" lines.
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
require_once($CFG->dirroot . '/mod/lti/locallib.php');
require_once(__DIR__ . '/seedlib.php');

$env = static function (string $name): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        cli_error("Missing environment variable $name (run seed-provider.php first).");
    }
    return $value;
};

\core\session\manager::set_user(get_admin());
flexaccess_lti_seed_site_settings();
set_config('enrol_plugins_enabled', 'manual,guest,self,cohort,flexaccess');

$typeid = lti_add_type((object) [
    'state' => LTI_TOOL_STATE_CONFIGURED,
    'course' => SITEID,
    'coursevisible' => LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
], (object) [
    'lti_typename' => 'FlexAccess provider',
    'lti_toolurl' => $env('LTI_PROVIDER_LAUNCH_URL'),
    'lti_ltiversion' => LTI_VERSION_1P3,
    'lti_keytype' => LTI_JWK_KEYSET,
    'lti_publickeyset' => $env('LTI_PROVIDER_JWKS_URL'),
    'lti_initiatelogin' => $env('LTI_PROVIDER_LOGIN_URL'),
    'lti_redirectionuris' => $env('LTI_PROVIDER_LAUNCH_URL'),
    'lti_launchcontainer' => LTI_LAUNCH_CONTAINER_WINDOW,
    'lti_sendname' => LTI_SETTING_ALWAYS,
    'lti_sendemailaddr' => LTI_SETTING_ALWAYS,
    'lti_acceptgrades' => LTI_SETTING_ALWAYS,
    'lti_coursevisible' => LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
    'lti_contentitem' => 0,
    'ltiservice_gradesynchronization' => 2,
    'ltiservice_memberships' => 1,
    'ltiservice_toolsettings' => 0,
]);
$type = $DB->get_record('lti_types', ['id' => $typeid], '*', MUST_EXIST);

if ($DB->record_exists('course', ['shortname' => 'PLATFORM'])) {
    cli_error('Already seeded: run on a fresh site.');
}
$course = create_course((object) [
    'fullname' => 'Platform course',
    'shortname' => 'PLATFORM',
    'category' => \core_course_category::get_default()->id,
    'visible' => 1,
]);
$flexenrolid = enrol_get_plugin('flexaccess')->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
\enrol_flexaccess\local\instance_config::save($flexenrolid, ['allowtemporary' => 1, 'allowquick' => 1]);
\cache::make('enrol_flexaccess', 'policy')->purge();

$activity = static function (string $name, string $uuid) use ($course, $typeid, $env): stdClass {
    return create_module((object) [
        'modulename' => 'lti',
        'course' => $course->id,
        'section' => 0,
        'name' => $name,
        'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
        'typeid' => $typeid,
        'toolurl' => $env('LTI_PROVIDER_LAUNCH_URL'),
        'instructorcustomparameters' => 'id=' . $uuid,
        'launchcontainer' => LTI_LAUNCH_CONTAINER_WINDOW,
        'grade' => 100,
        'instructorchoicesendname' => 1,
        'instructorchoicesendemailaddr' => 1,
        'instructorchoiceacceptgrades' => 1,
        'visible' => 1,
    ]);
};
$prompt = $activity('Provider resource (link your account)', $env('LTI_PROVIDER_PROMPT_UUID'));
$auto = $activity('Provider resource (automatic account)', $env('LTI_PROVIDER_AUTO_UUID'));
// The same resource embedded in the course page (iframe launch, third-party cookie context).
$embedded = $activity('Provider resource (embedded)', $env('LTI_PROVIDER_AUTO_UUID'));
$DB->set_field('lti', 'launchcontainer', LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS, ['id' => $embedded->instance]);

$learners = [];
$names = ['robin' => 'Robin', 'tara' => 'Tara', 'alex' => 'Alex', 'sam' => 'Sam', 'pat' => 'Pat', 'iris' => 'Iris'];
foreach ($names as $key => $first) {
    $email = $key . '@example.org';
    $learners[$key] = flexaccess_lti_seed_durable_user($key . '.platform', $first, 'Platform', $email, 'Platform-Pass!1');
    \enrol_flexaccess\local\enrol_service::admin_enrol((int) $course->id, $learners[$key]);
}
$learner = $learners['robin'];

flexaccess_lti_seed_export([
    'LTI_PLATFORM_ISSUER' => $CFG->wwwroot,
    'LTI_PLATFORM_CLIENTID' => $type->clientid,
    'LTI_PLATFORM_DEPLOYMENTID' => $typeid,
    'LTI_PLATFORM_AUTH_URL' => (new moodle_url('/mod/lti/auth.php'))->out(false),
    'LTI_PLATFORM_TOKEN_URL' => (new moodle_url('/mod/lti/token.php'))->out(false),
    'LTI_PLATFORM_JWKS_URL' => (new moodle_url('/mod/lti/certs.php'))->out(false),
    'LTI_PLATFORM_COURSE_ID' => $course->id,
    'LTI_PLATFORM_PROMPT_CMID' => $prompt->coursemodule,
    'LTI_PLATFORM_AUTO_CMID' => $auto->coursemodule,
    'LTI_PLATFORM_PROMPT_LTIID' => $prompt->instance,
    'LTI_PLATFORM_AUTO_LTIID' => $auto->instance,
    'LTI_PLATFORM_LEARNER_USER' => 'robin.platform',
    'LTI_PLATFORM_LEARNER_PASS' => 'Platform-Pass!1',
    'LTI_PLATFORM_LEARNER_ID' => $learner,
    'LTI_PLATFORM_EMBED_CMID' => $embedded->coursemodule,
    'LTI_PLATFORM_PASS' => 'Platform-Pass!1',
    'LTI_PLATFORM_TARA_ID' => $learners['tara'],
    'LTI_PLATFORM_ALEX_ID' => $learners['alex'],
    'LTI_PLATFORM_SAM_ID' => $learners['sam'],
    'LTI_PLATFORM_PAT_ID' => $learners['pat'],
    'LTI_PLATFORM_IRIS_ID' => $learners['iris'],
]);
