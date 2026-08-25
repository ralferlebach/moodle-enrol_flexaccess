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

use core\check\result;
use enrol_flexaccess\check\coupling;

/**
 * Tests for the auth/enrol coupling status check.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_flexaccess\check\coupling
 */
final class coupling_check_test extends \advanced_testcase {
    /**
     * Set the site enable state of the auth and enrol FlexAccess plugins.
     *
     * @param bool $auth Whether auth_flexaccess is enabled.
     * @param bool $enrol Whether enrol_flexaccess is enabled.
     */
    private function set_enabled(bool $auth, bool $enrol): void {
        set_config('auth', $auth ? 'email,flexaccess' : 'email');
        set_config('enrol_plugins_enabled', $enrol ? 'manual,flexaccess' : 'manual');
        \core_plugin_manager::reset_caches();
    }

    public function test_both_enabled_is_ok(): void {
        $this->resetAfterTest();
        $this->set_enabled(true, true);
        $this->assertSame(result::OK, (new coupling())->get_result()->get_status());
    }

    public function test_both_disabled_is_na(): void {
        $this->resetAfterTest();
        $this->set_enabled(false, false);
        $this->assertSame(result::NA, (new coupling())->get_result()->get_status());
    }

    public function test_enrol_only_is_error(): void {
        $this->resetAfterTest();
        $this->set_enabled(false, true);
        $this->assertSame(result::ERROR, (new coupling())->get_result()->get_status());
    }

    public function test_auth_only_is_warning(): void {
        $this->resetAfterTest();
        $this->set_enabled(true, false);
        $this->assertSame(result::WARNING, (new coupling())->get_result()->get_status());
    }

    public function test_check_is_registered(): void {
        $this->resetAfterTest();
        $checks = enrol_flexaccess_status_checks();
        $this->assertContainsOnlyInstancesOf(\core\check\check::class, $checks);
        $found = false;
        foreach ($checks as $check) {
            if ($check instanceof coupling) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'enrol_flexaccess_status_checks() must register the coupling check.');
    }
}
