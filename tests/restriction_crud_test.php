<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace enrol_flexaccess;

use enrol_flexaccess\local\restriction_service;

/**
 * Administration API behind the course restriction management page.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_flexaccess\local\restriction_service
 */
final class restriction_crud_test extends \advanced_testcase {
    public function test_add_is_idempotent_and_scoped(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $courseid = (int) $course->id;

        $first = restriction_service::add('course', $courseid, restriction_service::KIND_ROLE, 5, 'allow');
        $again = restriction_service::add('course', $courseid, restriction_service::KIND_ROLE, 5, 'allow');

        // An identical rule is not duplicated.
        $this->assertSame($first, $again);
        $this->assertCount(1, restriction_service::for_scope('course', $courseid));
        // The rule belongs to this course only.
        $this->assertCount(0, restriction_service::for_scope('course', (int) $other->id));
    }

    public function test_delete_refuses_a_foreign_scope(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $courseid = (int) $course->id;
        $systemrule = restriction_service::add('system', 0, restriction_service::KIND_COHORT, 7, 'deny');

        // A course-scoped delete must not reach a system rule.
        $this->assertFalse(restriction_service::delete($systemrule, 'course', $courseid));
        $this->assertCount(1, restriction_service::for_scope('system', 0));

        // Deleting it at its own scope works.
        $this->assertTrue(restriction_service::delete($systemrule, 'system', 0));
        $this->assertCount(0, restriction_service::for_scope('system', 0));
    }
}
