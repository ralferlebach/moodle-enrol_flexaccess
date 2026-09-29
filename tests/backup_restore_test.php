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

namespace enrol_flexaccess;

use enrol_flexaccess\local\instance_config;

/**
 * Backup, restore and duplication keep the FlexAccess method (checklist section 11).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_flexaccess_plugin
 * @covers \backup_enrol_flexaccess_plugin
 * @covers \restore_enrol_flexaccess_plugin
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Load the backup/restore libraries.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
    }

    /**
     * Back up a course (with users) and restore it into a target course.
     *
     * @param int $courseid Source course.
     * @param int $targetid Target course.
     * @param int $target backup::TARGET_* constant.
     * @return void
     */
    private function backup_and_restore(int $courseid, int $targetid, int $target): void {
        global $CFG, $USER;
        // Keep the backup directory for the restore that follows (as core's own backup tests do).
        $CFG->keeptempdirectoriesonbackup = true;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        $rc = new \restore_controller($backupid, $targetid, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id, $target);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
    }

    /**
     * A course with a configured FlexAccess method and one enrolled user.
     *
     * @return array [course, user]
     */
    private function configured_course(): array {
        global $DB;
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $enrolid = enrol_get_plugin('flexaccess')->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        instance_config::save($enrolid, ['allowtemporary' => 1, 'allowquick' => 1, 'maxparticipants' => 30]);
        $DB->set_field('enrol_flexaccess_instance', 'quickreggatemode', 'password', ['enrolid' => $enrolid]);
        $hash = local\quickreg_gate::hash('secret');
        $DB->set_field('enrol_flexaccess_instance', 'quickreggatepasswordhash', $hash, ['enrolid' => $enrolid]);
        $user = $this->getDataGenerator()->create_user();
        $this->assertTrue(local\enrol_service::admin_enrol((int) $course->id, (int) $user->id));
        return [$course, $user];
    }

    /**
     * Restoring into a new course keeps the method, its configuration and the enrolment.
     *
     * @return void
     */
    public function test_restore_into_new_course_keeps_method_and_configuration(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $user] = $this->configured_course();
        $source = $DB->get_record('enrol_flexaccess_instance', ['enrolid' => $DB->get_field(
            'enrol',
            'id',
            ['courseid' => $course->id, 'enrol' => 'flexaccess']
        )]);

        $newcourseid = \restore_dbops::create_new_course('Copy', 'COPY' . $course->id, (int) $course->category);
        $this->backup_and_restore((int) $course->id, (int) $newcourseid, \backup::TARGET_NEW_COURSE);

        $instances = $DB->get_records('enrol', ['courseid' => $newcourseid, 'enrol' => 'flexaccess']);
        $this->assertCount(1, $instances);
        $instance = reset($instances);
        $config = $DB->get_record('enrol_flexaccess_instance', ['enrolid' => $instance->id], '*', MUST_EXIST);
        foreach (['allowtemporary', 'allowquick', 'maxparticipants', 'quickreggatemode', 'quickreggatepasswordhash'] as $field) {
            $this->assertEquals($source->$field, $config->$field, $field);
        }
        $this->assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]));
    }

    /**
     * Restoring into a course that already has a FlexAccess method creates no second one and keeps its
     * own configuration.
     *
     * @return void
     */
    public function test_merge_into_existing_course_keeps_its_own_method(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->configured_course();
        $target = $this->getDataGenerator()->create_course();
        $targetenrolid = enrol_get_plugin('flexaccess')->add_instance($target, ['status' => ENROL_INSTANCE_ENABLED]);
        instance_config::save($targetenrolid, ['allowtemporary' => 0, 'maxparticipants' => 5]);

        $this->backup_and_restore((int) $course->id, (int) $target->id, \backup::TARGET_EXISTING_ADDING);

        $this->assertSame(1, $DB->count_records('enrol', ['courseid' => $target->id, 'enrol' => 'flexaccess']));
        $config = $DB->get_record('enrol_flexaccess_instance', ['enrolid' => $targetenrolid], '*', MUST_EXIST);
        $this->assertEquals(5, $config->maxparticipants);
        $this->assertEquals(0, $config->allowtemporary);
    }
}
