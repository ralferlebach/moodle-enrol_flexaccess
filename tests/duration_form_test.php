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

/**
 * The enrolment configuration form and its duration fields on every supported Moodle version
 * (tool_flexaccess #11, COMPAT-006: Moodle 5.3 validates a duration's default unit strictly).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_flexaccess_plugin
 * @covers \enrol_flexaccess\local\instance_config
 */
final class duration_form_test extends \advanced_testcase {
    /**
     * The instance form builds (the duration elements accept their options) and shows stored values.
     */
    public function test_form_builds_and_shows_stored_durations(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/formslib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        local\instance_config::save($enrolid, ['temporarylifetime' => 3 * HOURSECS, 'enrolperiod' => 14 * DAYSECS]);
        $instance = $DB->get_record('enrol', ['id' => $enrolid]);

        $mform = new \MoodleQuickForm('enrolinstance', 'post', '');
        $plugin->edit_instance_form($instance, $mform, \context_course::instance((int) $course->id));
        foreach (['temporarylifetime', 'enrolperiod'] as $name) {
            $this->assertTrue($mform->elementExists($name), $name);
            $this->assertSame('duration', $mform->getElement($name)->getType());
        }
        // The stored values round-trip unchanged (seconds in, seconds out) on every version.
        $config = local\instance_config::load($enrolid);
        $this->assertSame(3 * HOURSECS, (int) $config->temporarylifetime);
        $this->assertSame(14 * DAYSECS, (int) $config->enrolperiod);
        $element = $mform->getElement('enrolperiod');
        $submitted = ['enrolperiod' => ['number' => 14, 'timeunit' => DAYSECS, 'enabled' => 1]];
        $this->assertSame(14 * DAYSECS, (int) $element->exportValue($submitted, true)['enrolperiod']);
    }

    /**
     * Saving through the plugin API keeps the durations exactly.
     */
    public function test_durations_round_trip_through_update_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        $instance = $DB->get_record('enrol', ['id' => $enrolid]);
        $plugin->update_instance($instance, (object) [
            'status' => ENROL_INSTANCE_ENABLED,
            'temporarylifetime' => 90 * MINSECS,
            'enrolperiod' => WEEKSECS,
            'allowtemporary' => 1,
        ]);
        $config = local\instance_config::load($enrolid);
        $this->assertSame(90 * MINSECS, (int) $config->temporarylifetime);
        $this->assertSame(WEEKSECS, (int) $config->enrolperiod);
    }
}
