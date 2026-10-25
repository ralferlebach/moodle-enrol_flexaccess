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
 * FlexAccess participants in Core's gradebook, completion, grade import and LTI (enrol_flexaccess #8).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use enrol_flexaccess\local\enrol_service;
use enrol_flexaccess\local\participant_role;
use enrol_flexaccess\local\readiness;

/**
 * Gradebook role registration tests against the Core consumers themselves.
 *
 * @package    enrol_flexaccess
 * @covers \enrol_flexaccess\local\participant_role
 * @covers \enrol_flexaccess\local\readiness
 */
final class gradebook_role_test extends \advanced_testcase {
    /**
     * Enable FlexAccess for every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
    }

    /**
     * Remove the participant role from $CFG->gradebookroles (state of sites installed before 2026101700).
     *
     * @return void
     */
    private function unregister(): void {
        $ids = array_diff(participant_role::gradebook_roleids(), [participant_role::get_id()]);
        set_config('gradebookroles', implode(',', $ids));
    }

    /**
     * Role id by shortname.
     *
     * @param string $shortname Shortname.
     * @return int
     */
    private function roleid(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * A course with completion, one FlexAccess participant and one manual student.
     *
     * @return array [course, flexaccess user, manual student]
     */
    private function course_with_participants(): array {
        set_config('enablecompletion', 1);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $flex = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->assertTrue(enrol_service::admin_enrol((int) $course->id, (int) $flex->id));
        $this->getDataGenerator()->enrol_user((int) $student->id, (int) $course->id, 'student', 'manual');
        return [$course, $flex, $student];
    }

    /**
     * A fresh installation registers the participant role next to the student role.
     */
    public function test_fresh_install_registers_participant_role(): void {
        $listed = participant_role::gradebook_roleids();
        $this->assertContains(participant_role::get_id(), $listed);
        $this->assertContains($this->roleid('student'), $listed);
        $this->assertNotContains('participantnotgradeable', readiness::role_model_problems());
    }

    /**
     * The upgrade step registers the role on an existing site and keeps every other gradebook role.
     */
    public function test_upgrade_step_registers_role_and_keeps_others(): void {
        global $CFG;
        $teacher = $this->roleid('teacher');
        $this->unregister();
        set_config('gradebookroles', implode(',', array_merge(participant_role::gradebook_roleids(), [$teacher])));
        $before = participant_role::gradebook_roleids();
        $this->assertContains('participantnotgradeable', readiness::role_model_problems());

        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/enrol/flexaccess/db/upgrade.php');
        set_config('version', 2026092900, 'enrol_flexaccess');
        $this->assertTrue(xmldb_enrol_flexaccess_upgrade(2026092900));

        $after = participant_role::gradebook_roleids();
        $this->assertSame(array_merge($before, [participant_role::get_id()]), $after, 'Order and other ids kept.');
        $this->assertGreaterThanOrEqual(2026101700, (int) get_config('enrol_flexaccess', 'version'));
        $this->assertNotContains('participantnotgradeable', readiness::role_model_problems());
    }

    /**
     * A second synchronisation changes nothing and creates no duplicate.
     */
    public function test_sync_is_idempotent(): void {
        global $CFG;
        $this->unregister();
        $this->assertTrue(participant_role::sync_gradebook_role());
        $once = $CFG->gradebookroles;
        $this->assertFalse(participant_role::sync_gradebook_role());
        $this->assertSame($once, $CFG->gradebookroles);
        $this->assertSame(1, substr_count(',' . $once . ',', ',' . participant_role::get_id() . ','));
    }

    /**
     * A site that grades no student role keeps its deliberate policy and reports nothing.
     */
    public function test_site_without_student_grading_is_respected(): void {
        global $CFG;
        set_config('gradebookroles', (string) $this->roleid('teacher'));
        $this->assertFalse(participant_role::gradebook_role_missing());
        $this->assertFalse(participant_role::sync_gradebook_role());
        $this->assertSame((string) $this->roleid('teacher'), $CFG->gradebookroles);
        $this->assertNotContains('participantnotgradeable', readiness::role_model_problems());
    }

    /**
     * The grader report population contains FlexAccess participants only when the role is registered.
     */
    public function test_grader_report_population(): void {
        global $CFG;
        require_once($CFG->dirroot . '/grade/lib.php');
        require_once($CFG->dirroot . '/grade/report/grader/lib.php');
        $this->setAdminUser();
        [$course, $flex, $student] = $this->course_with_participants();
        $context = \context_course::instance((int) $course->id);
        $load = static function () use ($course, $context): array {
            $gpr = new \grade_plugin_return(['type' => 'report', 'plugin' => 'grader', 'courseid' => $course->id]);
            $report = new \grade_report_grader((int) $course->id, $gpr, $context);
            return array_map('intval', array_keys($report->load_users()));
        };
        $this->unregister();
        $this->assertNotContains((int) $flex->id, $load(), 'Reproduces the defect without the registration.');
        $this->assertContains((int) $student->id, $load());

        readiness::repair_role_model();
        $this->assertContains((int) $flex->id, $load());
        $this->assertContains((int) $student->id, $load());
    }

    /**
     * Completion: tracked users and the completion records the daily task initialises.
     *
     * get_tracked_users() selects by the capability moodle/course:isincompletionreports, which the
     * student-archetype participant role carries; the daily completion task, which creates the
     * course_completions records (time enrolled, progress start), filters by $CFG->gradebookroles.
     */
    public function test_completion_tracking_and_records(): void {
        global $DB;
        [$course, $flex] = $this->course_with_participants();
        $info = new \completion_info($course);
        $this->assertArrayHasKey((int) $flex->id, $info->get_tracked_users());
        $this->assertTrue($info->is_tracked_user((int) $flex->id));

        $run = static function () use ($DB, $course): void {
            $DB->delete_records('course_completions', ['course' => $course->id]);
            $task = new \core\task\completion_daily_task();
            ob_start();
            $task->execute();
            ob_end_clean();
        };
        $this->unregister();
        $run();
        $this->assertFalse(
            $DB->record_exists('course_completions', ['course' => $course->id, 'userid' => $flex->id]),
            'Reproduces the defect without the registration.'
        );
        $this->assertTrue(participant_role::sync_gradebook_role());
        $run();
        $this->assertTrue($DB->record_exists('course_completions', ['course' => $course->id, 'userid' => $flex->id]));
        $this->assertArrayHasKey((int) $flex->id, $info->get_tracked_users());
    }

    /**
     * LTI gradebook services accept FlexAccess participants as gradable.
     */
    public function test_lti_gradebook_services_accept_participant(): void {
        [$course, $flex] = $this->course_with_participants();
        $service = '\ltiservice_gradebookservices\local\service\gradebookservices';
        $this->unregister();
        $this->assertFalse($service::is_user_gradable_in_course((int) $course->id, (int) $flex->id));
        participant_role::sync_gradebook_role();
        $this->assertTrue($service::is_user_gradable_in_course((int) $course->id, (int) $flex->id));
    }

    /**
     * Grade import does not classify FlexAccess participants as "not enrolled".
     */
    public function test_grade_import_accepts_participant(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/grade/import/lib.php');
        $this->setAdminUser();
        [$course, $flex] = $this->course_with_participants();
        $item = $this->getDataGenerator()->create_grade_item(['courseid' => $course->id]);
        $importcode = get_new_importcode();
        $DB->insert_record('grade_import_values', (object) [
            'itemid' => $item->id,
            'userid' => $flex->id,
            'finalgrade' => 50,
            'importcode' => $importcode,
            'importer' => $USER->id,
        ]);
        $this->unregister();
        $unenrolled = get_unenrolled_users_in_import($importcode, (int) $course->id);
        $this->assertNotEmpty($unenrolled, 'Reproduces the defect without the registration.');
        participant_role::sync_gradebook_role();
        $this->assertEmpty(get_unenrolled_users_in_import($importcode, (int) $course->id));
    }

    /**
     * Analytics and archetype semantics stay as they were.
     */
    public function test_archetype_semantics_unchanged(): void {
        global $DB;
        $roleid = participant_role::get_id();
        $this->assertSame('student', $DB->get_field('role', 'archetype', ['id' => $roleid]));
        $this->assertArrayHasKey($roleid, get_archetype_roles('student'));
    }
}
