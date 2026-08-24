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
 * Tests for the FlexAccess access-window logic.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use enrol_flexaccess\local\access_window;
use enrol_flexaccess\local\policy;

/**
 * Access-window tests.
 *
 * @package    enrol_flexaccess
 * @covers     \enrol_flexaccess\local\access_window
 */
final class access_window_test extends \advanced_testcase {
    /**
     * An unbounded window (0/0) is always open.
     */
    public function test_unbounded_is_always_open(): void {
        $this->assertTrue(access_window::is_open(0, 0, 0));
        $this->assertTrue(access_window::is_open(0, 0, 1000000000));
    }

    /**
     * A window with both bounds is open only inside, lower inclusive, upper exclusive.
     */
    public function test_bounded_window_boundaries(): void {
        $from = 2000;
        $until = 3000;
        $this->assertFalse(access_window::is_open($from, $until, 1999));
        $this->assertTrue(access_window::is_open($from, $until, 2000));  // Lower inclusive.
        $this->assertTrue(access_window::is_open($from, $until, 2999));
        $this->assertFalse(access_window::is_open($from, $until, 3000)); // Upper exclusive.
        $this->assertFalse(access_window::is_open($from, $until, 3001));
    }

    /**
     * Only a lower bound is set.
     */
    public function test_only_from(): void {
        $this->assertFalse(access_window::is_open(2000, 0, 1999));
        $this->assertTrue(access_window::is_open(2000, 0, 2000));
        $this->assertTrue(access_window::is_open(2000, 0, 999999));
    }

    /**
     * Only an upper bound is set.
     */
    public function test_only_until(): void {
        $this->assertTrue(access_window::is_open(0, 3000, 0));
        $this->assertTrue(access_window::is_open(0, 3000, 2999));
        $this->assertFalse(access_window::is_open(0, 3000, 3000));
    }

    /**
     * Range validity: both unset is valid; from must precede until when both set.
     */
    public function test_is_valid_range(): void {
        $this->assertTrue(access_window::is_valid_range(0, 0));
        $this->assertTrue(access_window::is_valid_range(2000, 0));
        $this->assertTrue(access_window::is_valid_range(0, 3000));
        $this->assertTrue(access_window::is_valid_range(2000, 3000));
        $this->assertFalse(access_window::is_valid_range(3000, 2000));
        $this->assertFalse(access_window::is_valid_range(2000, 2000));
        $this->assertFalse(access_window::is_valid_range(-1, 0));
    }

    /**
     * The policy value object delegates to the window logic.
     */
    public function test_policy_is_within_window(): void {
        $p = new policy();
        $p->availablefrom = 2000;
        $p->availableuntil = 3000;
        $this->assertFalse($p->is_within_window(1999));
        $this->assertTrue($p->is_within_window(2500));
        $this->assertFalse($p->is_within_window(3000));
    }
}
