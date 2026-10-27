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
 * Server-side probe and fixture actions of the two-site LTI 1.3 suite (test sites only).
 *
 * Run inside one of the two sites; prints one JSON value. The browser journeys use it to read the
 * state a journey must have produced (bindings, grades, enrolment states) and for the steps that are
 * not browser interactions on a real site either (scheduled tasks, an administrator's merge).
 *
 * Usage: php cli.php <command> [args...]
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/auth/lti/auth.php');
require_once($CFG->libdir . '/completionlib.php');

[$command, $args] = [$argv[1] ?? '', array_slice($argv, 2)];
\core\session\manager::set_user(get_admin());

$userid = static function (string $username): int {
    global $DB;
    return (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
};
$ue = static function (int $uid, int $courseid, string $plugin): ?stdClass {
    global $DB;
    return $DB->get_record_sql(
        "SELECT ue.* FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
          WHERE ue.userid = :u AND e.courseid = :c AND e.enrol = :p",
        ['u' => $uid, 'c' => $courseid, 'p' => $plugin]
    ) ?: null;
};

switch ($command) {
    case 'userid':
        $out = $userid($args[0]);
        break;
    case 'binding':
        // The local user bound to a platform identity, or 0.
        $out = (int) (new auth_plugin_lti())->get_user_binding($args[0], $args[1]);
        break;
    case 'user':
        $user = $DB->get_record('user', ['id' => (int) $args[0]], 'id, username, auth, suspended, deleted, email');
        $account = $DB->get_record('auth_flexaccess_account', ['userid' => (int) $args[0]]);
        $out = [
            'user' => $user,
            'accounttype' => $account->accounttype ?? null,
            'accountstate' => $account->accountstate ?? null,
        ];
        break;
    case 'countauth':
        $out = $DB->count_records('user', ['auth' => $args[0], 'deleted' => 0]);
        break;
    case 'ue':
        $row = $ue((int) $args[0], (int) $args[1], $args[2]);
        $out = $row ? [
            'status' => (int) $row->status,
            'timestart' => (int) $row->timestart,
            'timeend' => (int) $row->timeend,
        ] : null;
        break;
    case 'expireflexaccess':
        $row = $ue((int) $args[0], (int) $args[1], 'flexaccess');
        $DB->set_field('user_enrolments', 'timeend', \core\di::get(\core\clock::class)->time() - 60, ['id' => $row->id]);
        \enrol_flexaccess\local\enrol_expiry::process();
        $out = 'expired';
        break;
    case 'suspend':
        $DB->set_field('user', 'suspended', (int) $args[1], ['id' => (int) $args[0]]);
        if ((int) $args[1] === 1) {
            \core\session\manager::destroy_user_sessions((int) $args[0]);
        }
        $out = 'ok';
        break;
    case 'setgrade':
        // Grade the published assignment for a user, as a teacher on the provider would.
        $assign = $DB->get_record('assign', ['id' => (int) $args[0]], '*', MUST_EXIST);
        $result = grade_update('mod/assign', $assign->course, 'mod', 'assign', $assign->id, 0, [
            (int) $args[1] => (object) ['userid' => (int) $args[1], 'rawgrade' => (float) $args[2]],
        ]);
        // The course total as the gradebook shows it (course resources return the course grade).
        grade_regrade_final_grades($assign->course);
        $out = $result === GRADE_UPDATE_OK ? 'ok' : 'failed';
        break;
    case 'lineitemgrade':
        // The grade the platform holds for a learner in an external-tool activity.
        $item = grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'lti', 'iteminstance' => (int) $args[0]]);
        $grade = $item ? $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => (int) $args[1]]) : null;
        $out = $grade && $grade->finalgrade !== null ? (float) $grade->finalgrade : null;
        break;
    case 'task':
        $task = \core\task\manager::get_scheduled_task($args[0]);
        ob_start();
        $task->execute();
        // Tasks like sync_grades and sync_members only queue ad-hoc tasks: run those as cron would. With 'later',
        // the cron run comes after the retry delay of failed ad-hoc tasks has passed (a delayed cron run).
        $now = \core\di::get(\core\clock::class)->time();
        if (($args[1] ?? '') === 'later') {
            $DB->set_field_select('task_adhoc', 'nextruntime', $now - 1, 'faildelay > 0 AND nextruntime >= ?', [$now]);
        }
        \core\cron::run_adhoc_tasks($now, 0, false);
        $log = ob_get_clean();
        $out = ['ran' => $args[0], 'log' => substr((string) $log, -2000)];
        break;
    case 'approve':
        $out = \auth_flexaccess\api::approve_merge(
            (int) $args[0],
            (int) $args[1],
            true,
            'reauthentication',
            'The learner logged in with both accounts during the two-site E2E journey.'
        );
        break;
    case 'merge':
        // An administrator's merge with tool_mergeusers: keep args[0], remove args[1].
        if (!class_exists(\tool_mergeusers\local\user_merger::class)) {
            $out = ['success' => false, 'error' => 'tool_mergeusers is not installed'];
            break;
        }
        [$success, $log] = (new \tool_mergeusers\local\user_merger())->merge((int) $args[0], (int) $args[1]);
        // What the queued reconcile_merge task does after the commit.
        \auth_flexaccess\local\merge_service::verify((int) $args[1], (int) $args[0]);
        $registry = \auth_flexaccess\local\merge_registry::get((int) $args[1], (int) $args[0]);
        $out = ['success' => (bool) $success, 'registry' => $registry];
        break;
    case 'ltimembers':
        $out = $DB->count_records('enrol_lti_users', ['userid' => (int) $args[0]]);
        break;
    case 'newestflexaccessuser':
        // The most recently created FlexAccess user (a visitor who just took temporary access).
        $out = (int) $DB->get_field_sql("SELECT MAX(id) FROM {user} WHERE auth = 'flexaccess' AND deleted = 0");
        break;
    case 'persist':
        // The visitor makes the temporary account permanent (what persist.php does after the form).
        $status = \auth_flexaccess\api::persist_temporary_user((int) $args[0], $args[1], $args[2], $args[3], $args[4]);
        $out = ['status' => $status, 'username' => $DB->get_field('user', 'username', ['id' => (int) $args[0]])];
        break;
    case 'accountstate':
        // An account state as the lifecycle would leave it before the scheduled task runs.
        $DB->set_field('auth_flexaccess_account', 'accountstate', $args[1], ['userid' => (int) $args[0]]);
        $out = 'ok';
        break;
    case 'provisioning':
        // Change the learner provisioning mode of a published resource, as a teacher would.
        $DB->set_field('enrol_lti_tools', 'provisioningmodelearner', (int) $args[1], ['id' => (int) $args[0]]);
        $out = 'ok';
        break;
    case 'complete':
        // The learner marks the (manually completed) activity as done.
        $cm = get_coursemodule_from_id(false, (int) $args[0], 0, false, MUST_EXIST);
        (new completion_info(get_course($cm->course)))->update_state($cm, COMPLETION_COMPLETE, (int) $args[1]);
        $out = (int) (new completion_info(get_course($cm->course)))->get_data($cm, false, (int) $args[1])->completionstate;
        break;
    case 'completioncron':
        // Course completion is aggregated by its scheduled task, as cron would; a delayed run is just a later one.
        ob_start();
        \core\task\manager::get_scheduled_task('\\core\\task\\completion_regular_task')->execute();
        ob_end_clean();
        $out = (bool) (new completion_info(get_course((int) $args[0])))->is_course_complete((int) $args[1]);
        break;
    case 'ags':
        // Make the platform's token endpoint unreachable for the provider ('broken') or restore it ('ok'):
        // grade delivery fails like during a platform outage.
        $key = 'flexaccess_e2e_tokenurl';
        foreach ($DB->get_records('enrol_lti_app_registration') as $reg) {
            if ($args[0] === 'broken') {
                set_config($key . $reg->id, $reg->accesstokenurl, 'enrol_flexaccess');
                $unreachable = 'https://platform.test:8443/unreachable';
                $DB->set_field('enrol_lti_app_registration', 'accesstokenurl', $unreachable, ['id' => $reg->id]);
            } else if ($saved = get_config('enrol_flexaccess', $key . $reg->id)) {
                $DB->set_field('enrol_lti_app_registration', 'accesstokenurl', $saved, ['id' => $reg->id]);
                unset_config($key . $reg->id, 'enrol_flexaccess');
            }
        }
        $out = $args[0];
        break;
    case 'retries':
        // Grade deliveries that failed and wait for Core's retry (ad-hoc task with a fail delay).
        $out = $DB->count_records_select('task_adhoc', 'classname = ? AND faildelay > 0', [
            '\\enrol_lti\\local\\ltiadvantage\\task\\sync_tool_grades',
        ]);
        break;
    case 'lastgrade':
        // What the provider recorded as delivered for a learner of a published resource (0: nothing yet).
        $row = $DB->get_record('enrol_lti_users', ['toolid' => (int) $args[0], 'userid' => (int) $args[1]]);
        $out = $row && $row->lastgrade !== null ? (float) $row->lastgrade : null;
        break;
    case 'recover':
        // The FlexAccess enrolment of a learner expires and an administrator recovers it (tool_flexaccess).
        $row = $ue((int) $args[0], (int) $args[1], 'flexaccess');
        $DB->set_field('user_enrolments', 'timeend', \core\di::get(\core\clock::class)->time() - 60, ['id' => $row->id]);
        \enrol_flexaccess\local\enrol_expiry::process();
        $expired = (int) $ue((int) $args[0], (int) $args[1], 'flexaccess')->status;
        $result = \tool_flexaccess\local\recovery::recover((int) $args[0], (int) $args[1], ['reactivate' => [(int) $row->id]]);
        $out = [
            'expired' => $expired,
            'overall' => $result->overall,
            'status' => (int) $ue((int) $args[0], (int) $args[1], 'flexaccess')->status,
        ];
        break;
    case 'sessionuser':
        // The user a browser session belongs to (0: the session no longer exists or is not signed in).
        $out = (int) $DB->get_field('sessions', 'userid', ['sid' => $args[0]]);
        break;
    case 'canlogin':
        $out = \auth_flexaccess\local\login_guard::evaluate((int) $args[0], 'password');
        break;
    default:
        cli_error("Unknown command '$command'.");
}
echo json_encode($out), "\n";
