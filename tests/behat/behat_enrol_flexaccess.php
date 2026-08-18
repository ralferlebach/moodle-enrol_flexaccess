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
 * Behat step definitions for FlexAccess ecosystem (cross-plugin) tests.
 *
 * These steps deliberately drive the real cross-plugin flow (enrol_flexaccess creating an account
 * through auth_flexaccess and enrolling it), so they only work when the sibling plugins are
 * installed. Ecosystem CI installs them via moodle-plugin-ci --extra-plugins.
 *
 * @package    enrol_flexaccess
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Cross-plugin FlexAccess step definitions.
 *
 * @package    enrol_flexaccess
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_enrol_flexaccess extends behat_base {
    /**
     * Grants a temporary FlexAccess account in a course via the real enrol access flow.
     *
     * @Given a FlexAccess temporary account is granted in course :coursefullname
     * @param string $coursefullname Full name of an existing course.
     * @return void
     */
    public function a_flexaccess_temporary_account_is_granted_in_course(string $coursefullname): void {
        global $DB;

        $courseid = (int) $DB->get_field('course', 'id', ['fullname' => $coursefullname], MUST_EXIST);
        $course = get_course($courseid);

        // The instance-level allowtemporary flag may only widen the category policy when widening is on.
        set_config('allowwidening', 1, 'enrol_flexaccess');

        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        $DB->set_field('enrol_flexaccess_instance', 'allowtemporary', 1, ['enrolid' => $enrolid]);
        $DB->set_field('enrol_flexaccess_instance', 'temporarylifetime', DAYSECS, ['enrolid' => $enrolid]);

        $result = \enrol_flexaccess\local\access_controller::grant_temporary_access($courseid);
        if ($result->status !== 'granted') {
            throw new \Exception('FlexAccess temporary access was not granted: ' . $result->status);
        }
    }

    /**
     * Configures a course with an enabled FlexAccess method that offers temporary access.
     *
     * @Given a FlexAccess enrolment method allowing temporary access exists in course :coursefullname
     * @param string $coursefullname Full name of an existing course.
     * @return void
     */
    public function a_flexaccess_method_allowing_temporary_access_exists_in_course(string $coursefullname): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['fullname' => $coursefullname], MUST_EXIST);
        $course = get_course($courseid);

        set_config('allowwidening', 1, 'enrol_flexaccess');
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        \enrol_flexaccess\local\instance_config::save($enrolid, [
            'allowtemporary' => 1,
            'temporarylifetime' => DAYSECS,
        ]);
    }

    /**
     * Opens the anonymous FlexAccess entry page for a course.
     *
     * @When I open the FlexAccess entry page for course :coursefullname
     * @param string $coursefullname Full name of an existing course.
     * @return void
     */
    public function i_open_the_flexaccess_entry_page_for_course(string $coursefullname): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['fullname' => $coursefullname], MUST_EXIST);
        $url = new \moodle_url('/auth/flexaccess/access.php', ['courseid' => $courseid]);
        $this->execute('behat_general::i_visit', [$url]);
    }

    /**
     * Configures a course whose temporary access is gated by a course access key.
     *
     * @Given a FlexAccess enrolment method requiring access key :key exists in course :coursefullname
     * @param string $key Clear-text access key.
     * @param string $coursefullname Full name of an existing course.
     * @return void
     */
    public function a_flexaccess_method_requiring_access_key_exists_in_course(
        string $key,
        string $coursefullname
    ): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['fullname' => $coursefullname], MUST_EXIST);
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance(get_course($courseid), ['status' => ENROL_INSTANCE_ENABLED]);
        \enrol_flexaccess\local\instance_config::save($enrolid, [
            'allowtemporary' => 1,
            'temporarylifetime' => DAYSECS,
        ]);
        $DB->set_field('enrol_flexaccess_instance', 'temporaryaccesskeymode', 'course', ['enrolid' => $enrolid]);
        $DB->set_field(
            'enrol_flexaccess_instance',
            'temporaryaccesskeyhash',
            password_hash($key, PASSWORD_DEFAULT),
            ['enrolid' => $enrolid]
        );
    }
}
