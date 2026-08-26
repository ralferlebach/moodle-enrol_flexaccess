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
 * Tests for the FlexAccess access gate.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use enrol_flexaccess\local\access_gate;
use enrol_flexaccess\local\policy;

/**
 * Access-gate tests.
 *
 * @package    enrol_flexaccess
 * @covers \enrol_flexaccess\local\access_gate
 */
final class access_gate_test extends \advanced_testcase {
    /**
     * Build a policy enabling all methods with the given window/capacity.
     *
     * @param int $from Available-from timestamp.
     * @param int $until Available-until timestamp.
     * @param int $max Maximum number of participants.
     * @return policy
     */
    private function policy(int $from, int $until, int $max): policy {
        $p = new policy();
        $p->allowtemporary = true;
        $p->allowquick = true;
        $p->allowguest = true;
        $p->allownormallogin = true;
        $p->availablefrom = $from;
        $p->availableuntil = $until;
        $p->maxparticipants = $max;
        return $p;
    }

    /**
     * Inside the window with free capacity, all enabled methods are offerable.
     */
    public function test_open_and_free_offers_all(): void {
        $o = access_gate::offerable($this->policy(1000, 3000, 10), 2000, 5);
        $this->assertTrue($o->temporary);
        $this->assertTrue($o->quick);
        $this->assertTrue($o->guest);
        $this->assertTrue($o->normallogin);
    }

    /**
     * Outside the window, FlexAccess methods are blocked but normal login remains.
     */
    public function test_closed_window_blocks_flex_not_login(): void {
        $o = access_gate::offerable($this->policy(1000, 3000, 10), 3000, 0);
        $this->assertFalse($o->temporary);
        $this->assertFalse($o->quick);
        $this->assertFalse($o->guest);
        $this->assertTrue($o->normallogin);
    }

    /**
     * When full, enrolment-creating methods are blocked; guest and login remain.
     */
    public function test_full_blocks_enrolling_methods(): void {
        $o = access_gate::offerable($this->policy(0, 0, 5), 2000, 5);
        $this->assertFalse($o->temporary);
        $this->assertFalse($o->quick);
        $this->assertTrue($o->guest);
        $this->assertTrue($o->normallogin);
    }

    /**
     * Unlimited capacity never blocks on capacity.
     */
    public function test_unlimited_capacity(): void {
        $o = access_gate::offerable($this->policy(0, 0, 0), 2000, 999999);
        $this->assertTrue($o->temporary);
        $this->assertTrue($o->quick);
    }

    /**
     * has_any_method reflects at least one offerable path.
     */
    public function test_has_any_method(): void {
        $loginonly = new policy();
        $loginonly->allowtemporary = false;
        $loginonly->allowquick = false;
        $loginonly->allowguest = false;
        $loginonly->allownormallogin = true;
        $this->assertTrue(access_gate::has_any_method($loginonly, 2000, 0));
    }
}
