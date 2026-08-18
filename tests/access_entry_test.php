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
 * Tests for the FlexAccess anonymous-entry gate and instance configuration form persistence.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_flexaccess\api
 */
final class access_entry_test extends \advanced_testcase {
    /**
     * Create a course with an enabled FlexAccess instance and return [course, enrolid].
     *
     * @param array $config Extended configuration to persist on the instance.
     * @return array{0: \stdClass, 1: int}
     */
    private function course_with_instance(array $config = []): array {
        global $DB;
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        if ($config) {
            instance_config::save($enrolid, $config);
        }
        return [$course, $enrolid];
    }

    /**
     * A course only offers anonymous entry when a method is enabled and the window is open.
     *
     * @return void
     */
    public function test_offers_anonymous_entry(): void {
        $this->resetAfterTest();

        // No anonymous method enabled: not offered.
        [$course1] = $this->course_with_instance(['allownormallogin' => 1]);
        $this->assertFalse(api::offers_anonymous_entry((int) $course1->id));

        // Temporary access enabled: offered.
        [$course2] = $this->course_with_instance(['allowtemporary' => 1]);
        $this->assertTrue(api::offers_anonymous_entry((int) $course2->id));

        // A course without any FlexAccess instance is never offered.
        $bare = $this->getDataGenerator()->create_course();
        $this->assertFalse(api::offers_anonymous_entry((int) $bare->id));
    }

    /**
     * A closed access window suppresses anonymous entry even with a method enabled.
     *
     * @return void
     */
    public function test_window_closes_entry(): void {
        $this->resetAfterTest();
        $now = 1000000;
        [$course] = $this->course_with_instance([
            'allowtemporary' => 1,
            'availablefrom' => $now + DAYSECS,
        ]);
        $this->assertFalse(api::offers_anonymous_entry((int) $course->id, $now));
    }

    /**
     * The instance form fields are persisted and reloaded.
     *
     * @return void
     */
    public function test_instance_config_persists_fields(): void {
        $this->resetAfterTest();
        [, $enrolid] = $this->course_with_instance([
            'allowtemporary' => 1,
            'allowquick' => 1,
            'allowguest' => 0,
            'allownormallogin' => 0,
            'temporarylifetime' => 3600,
            'expiryaction' => 'unenrol',
        ]);
        $config = instance_config::load($enrolid);
        $this->assertSame(1, (int) $config->allowtemporary);
        $this->assertSame(1, (int) $config->allowquick);
        $this->assertSame(0, (int) $config->allowguest);
        $this->assertSame(0, (int) $config->allownormallogin);
        $this->assertSame(3600, (int) $config->temporarylifetime);
        $this->assertSame('unenrol', $config->expiryaction);
    }

    /**
     * Only one FlexAccess enrolment method is allowed per course.
     *
     * @return void
     */
    public function test_one_instance_per_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->course_with_instance(['allowtemporary' => 1]);
        $plugin = enrol_get_plugin('flexaccess');
        $this->assertFalse($plugin->can_add_instance((int) $course->id));

        $fresh = $this->getDataGenerator()->create_course();
        $this->assertTrue($plugin->can_add_instance((int) $fresh->id));
    }
}
