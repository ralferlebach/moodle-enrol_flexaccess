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

use enrol_flexaccess\local\category_policy;

/**
 * Tests for the category-level policy write path and its effect on the resolved policy.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_flexaccess\local\category_policy
 */
final class category_policy_test extends \advanced_testcase {
    /**
     * Saving an override persists it and it folds into the effective policy of a course in that category.
     *
     * @return void
     */
    public function test_save_load_and_effective_merge(): void {
        $this->resetAfterTest();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);

        category_policy::save((int) $category->id, [
            'allowtemporary' => 1,
            'allowquick' => 0,
            'allowguest' => -1,
            'allownormallogin' => -1,
            'temporarylifetime' => 3600,
            'provisionallifetime' => null,
            'participantlistaccess' => 'hide',
        ]);

        $row = category_policy::load((int) $category->id);
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->allowtemporary);
        $this->assertSame(0, (int) $row->allowquick);

        $policy = \enrol_flexaccess\api::get_effective_policy((int) $course->id);
        $this->assertTrue($policy->allowtemporary);
        $this->assertFalse($policy->allowquick);
        $this->assertSame('hide', $policy->participantlistaccess);
        $this->assertSame(3600, $policy->temporarylifetime);
    }

    /**
     * An all-inherit save stores nothing, and delete removes an existing override.
     *
     * @return void
     */
    public function test_empty_save_and_delete(): void {
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();

        // All-inherit: no row created.
        category_policy::save((int) $category->id, [
            'allowtemporary' => -1, 'allowquick' => -1, 'allowguest' => -1, 'allownormallogin' => -1,
            'temporarylifetime' => null, 'provisionallifetime' => null, 'participantlistaccess' => 'inherit',
        ]);
        $this->assertNull(category_policy::load((int) $category->id));

        // Create then delete.
        category_policy::save((int) $category->id, [
            'allowtemporary' => 0, 'allowquick' => -1, 'allowguest' => -1, 'allownormallogin' => -1,
            'temporarylifetime' => null, 'provisionallifetime' => null, 'participantlistaccess' => 'inherit',
        ]);
        $this->assertNotNull(category_policy::load((int) $category->id));
        $this->assertArrayHasKey((int) $category->id, category_policy::all());

        category_policy::delete((int) $category->id);
        $this->assertNull(category_policy::load((int) $category->id));
    }
}
