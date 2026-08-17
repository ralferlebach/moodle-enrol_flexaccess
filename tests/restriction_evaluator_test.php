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
 * Tests for the FlexAccess restriction evaluator.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use enrol_flexaccess\local\restriction_evaluator;

/**
 * Restriction evaluator tests.
 */
final class restriction_evaluator_test extends \advanced_testcase {
    /**
     * Build a restriction row.
     */
    private function rule(string $kind, int $refid, string $effect): \stdClass {
        return (object) ['kind' => $kind, 'refid' => $refid, 'effect' => $effect];
    }

    /**
     * No restrictions means permitted.
     */
    public function test_no_restrictions(): void {
        $this->assertTrue(restriction_evaluator::permit([], [3], [5]));
    }

    /**
     * A matching deny blocks, a non-matching deny does not.
     */
    public function test_deny(): void {
        $deny = [$this->rule('cohort', 5, 'deny')];
        $this->assertFalse(restriction_evaluator::permit($deny, [], [5]));
        $this->assertTrue(restriction_evaluator::permit($deny, [], [9]));
    }

    /**
     * With allow rules present, the user must match at least one.
     */
    public function test_allowlist(): void {
        $allow = [$this->rule('role', 3, 'allow'), $this->rule('role', 4, 'allow')];
        $this->assertTrue(restriction_evaluator::permit($allow, [4], []));
        $this->assertFalse(restriction_evaluator::permit($allow, [7], []));
    }

    /**
     * Deny wins over a matching allow.
     */
    public function test_deny_wins(): void {
        $rules = [$this->rule('role', 3, 'allow'), $this->rule('cohort', 5, 'deny')];
        $this->assertFalse(restriction_evaluator::permit($rules, [3], [5]));
    }
}
