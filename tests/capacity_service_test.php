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
 * Tests for the FlexAccess capacity service.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use enrol_flexaccess\local\capacity_service;

/**
 * Capacity service tests.
 *
 * @package    enrol_flexaccess
 * @covers \enrol_flexaccess\local\capacity_service
 */
final class capacity_service_test extends \advanced_testcase {
    /**
     * The pure predicate treats 0/negative maximum as unlimited.
     */
    public function test_has_free_capacity_predicate(): void {
        $this->assertTrue(capacity_service::has_free_capacity(1000, 0));   // Unlimited.
        $this->assertTrue(capacity_service::has_free_capacity(1000, -1));  // Unlimited.
        $this->assertTrue(capacity_service::has_free_capacity(0, 1));
        $this->assertTrue(capacity_service::has_free_capacity(4, 5));
        $this->assertFalse(capacity_service::has_free_capacity(5, 5));     // At limit.
        $this->assertFalse(capacity_service::has_free_capacity(6, 5));     // Over limit.
    }

    /**
     * Only active, non-expired user-enrolments count towards capacity.
     */
    public function test_count_active_enrolments(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');
        $instanceid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        $now = time();

        // Two plain active enrolments.
        for ($i = 0; $i < 2; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $plugin->enrol_user($instance, $user->id, null, 0, 0, ENROL_USER_ACTIVE);
        }
        // One active but already expired (timeend in the past) -> must not count.
        $expired = $this->getDataGenerator()->create_user();
        $plugin->enrol_user($instance, $expired->id, null, $now - 7200, $now - 3600, ENROL_USER_ACTIVE);
        // One suspended -> must not count.
        $suspended = $this->getDataGenerator()->create_user();
        $plugin->enrol_user($instance, $suspended->id, null, 0, 0, ENROL_USER_SUSPENDED);
        // One active with a future end -> must count.
        $future = $this->getDataGenerator()->create_user();
        $plugin->enrol_user($instance, $future->id, null, 0, $now + 3600, ENROL_USER_ACTIVE);

        $this->assertSame(3, capacity_service::count_active_enrolments($instanceid, $now));
        $this->assertFalse(capacity_service::is_full($instanceid, 0, $now));   // Unlimited.
        $this->assertFalse(capacity_service::is_full($instanceid, 4, $now));   // 3 < 4.
        $this->assertTrue(capacity_service::is_full($instanceid, 3, $now));    // 3 == 3.
        $this->assertTrue(capacity_service::is_full($instanceid, 2, $now));    // 3 > 2.
    }

    /**
     * The lock wrapper executes the critical section and returns its result.
     */
    public function test_run_with_lock_executes_callback(): void {
        $this->resetAfterTest();
        $ran = false;
        $result = capacity_service::run_with_lock(4711, function () use (&$ran) {
            $ran = true;
            return 'done';
        });
        $this->assertTrue($ran);
        $this->assertSame('done', $result);
    }
}
