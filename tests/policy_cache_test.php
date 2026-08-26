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

use PHPUnit\Framework\Attributes\CoversClass;
use enrol_flexaccess\local\instance_config;
use enrol_flexaccess\local\policy_assembler;

/**
 * Tests for the request-scoped policy cache and its invalidation.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_flexaccess\local\policy_assembler::class)]
final class policy_cache_test extends \advanced_testcase {
    /**
     * Add an enabled FlexAccess instance to a fresh course and return [courseid, enrolid].
     *
     * @param array $config Instance config overrides.
     * @return array{0:int,1:int}
     */
    private function make_instance(array $config = []): array {
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED] + $config);
        return [(int) $course->id, (int) $enrolid];
    }

    /**
     * A cached base policy is never mutated by callers: mutating one result must not affect the next.
     *
     * @return void
     */
    public function test_cache_returns_isolated_clones(): void {
        $this->resetAfterTest();
        [$courseid] = $this->make_instance(['allowtemporary' => 1]);

        $first = policy_assembler::assemble($courseid);
        $original = $first->allowtemporary;
        // Mutate the returned object; this must not leak into the cache.
        $first->allowtemporary = !$original;

        $second = policy_assembler::assemble($courseid);
        $this->assertSame($original, $second->allowtemporary);
    }

    /**
     * Saving an instance override in the same request is reflected by a later resolution (purge works).
     *
     * @return void
     */
    public function test_write_invalidates_within_request(): void {
        $this->resetAfterTest();
        [$courseid, $enrolid] = $this->make_instance(['allowquick' => 1]);

        // Warm the cache.
        $this->assertTrue(policy_assembler::assemble($courseid)->allowquick);

        // Change the instance; the write path must purge the cached course entry.
        instance_config::save($enrolid, ['allowquick' => 0]);
        $this->assertFalse(policy_assembler::assemble($courseid)->allowquick);
    }
}
