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
 * Tests for FlexAccess enrol instance-config persistence.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use PHPUnit\Framework\Attributes\CoversClass;
use enrol_flexaccess\local\instance_config;

/**
 * Instance-config persistence tests.
 *
 * @package    enrol_flexaccess
 */
#[CoversClass(\enrol_flexaccess\local\instance_config::class)]
final class instance_config_test extends \advanced_testcase {
    /**
     * Adding an instance persists the access window and capacity; delete removes it.
     */
    public function test_add_update_delete_persistence(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');

        $enrolid = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'availablefrom' => 1000,
            'availableuntil' => 2000,
            'maxparticipants' => 30,
        ]);

        $row = instance_config::load($enrolid);
        $this->assertNotNull($row);
        $this->assertSame(1000, (int) $row->availablefrom);
        $this->assertSame(2000, (int) $row->availableuntil);
        $this->assertSame(30, (int) $row->maxparticipants);

        // Update.
        $instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        $plugin->update_instance($instance, (object) [
            'availablefrom' => 0,
            'availableuntil' => 0,
            'maxparticipants' => 0,
            'roleid' => $instance->roleid,
        ]);
        $row = instance_config::load($enrolid);
        $this->assertSame(0, (int) $row->availablefrom);
        $this->assertSame(0, (int) $row->maxparticipants);
        // Exactly one extension row per instance.
        $this->assertEquals(1, $DB->count_records('enrol_flexaccess_instance', ['enrolid' => $enrolid]));

        // Delete.
        $plugin->delete_instance($instance);
        $this->assertNull(instance_config::load($enrolid));
    }
}
