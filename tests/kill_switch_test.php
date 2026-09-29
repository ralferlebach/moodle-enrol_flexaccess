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

use enrol_flexaccess\local\access_controller;
use enrol_flexaccess\local\instance_config;

/**
 * A disabled plugin stops every entry flow before any side effect (Lessons Learnt 3).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_flexaccess\local\access_gate
 * @covers \enrol_flexaccess\local\access_controller
 */
final class kill_switch_test extends \advanced_testcase {
    /**
     * Counts of everything an entry flow could create.
     *
     * @return array
     */
    private function footprint(): array {
        global $DB;
        return [
            $DB->count_records('user'),
            $DB->count_records('auth_flexaccess_account'),
            $DB->count_records('user_enrolments'),
            $DB->count_records('auth_flexaccess_mailqueue'),
        ];
    }

    /**
     * Data provider: which plugin is switched off.
     *
     * @return array
     */
    public static function switch_provider(): array {
        return [
            'enrolment plugin disabled' => ['enrol'],
            'authentication plugin disabled' => ['auth'],
        ];
    }

    /**
     * Temporary access, quick registration (also trusted: invitations, campaigns) and the gate step.
     *
     * @dataProvider switch_provider
     * @param string $off Which plugin is disabled.
     * @return void
     */
    public function test_disabled_plugin_stops_before_side_effects(string $off): void {
        global $DB;
        $this->resetAfterTest();
        if (!$DB->get_manager()->table_exists('auth_flexaccess_account')) {
            $this->markTestSkipped('Requires the auth_flexaccess sibling plugin to be installed.');
        }
        set_config('allowwidening', 1, 'enrol_flexaccess');
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        set_config('auth', 'flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $enrolid = local\enrol_service::ensure_instance((int) $course->id);
        instance_config::save($enrolid, ['allowtemporary' => 1, 'allowquick' => 1]);
        if ($off === 'enrol') {
            set_config('enrol_plugins_enabled', 'manual');
        } else {
            set_config('auth', '');
        }
        \cache::make('enrol_flexaccess', 'policy')->purge();

        $before = $this->footprint();
        $person = (object) ['email' => 'off@example.com', 'firstname' => 'O', 'lastname' => 'F', 'password' => 'Str0ng-Pass!23'];
        $this->assertSame('closed', access_controller::grant_temporary_access((int) $course->id)->status);
        $this->assertSame('closed', access_controller::grant_quick_registration((int) $course->id, $person)->status);
        $trusted = access_controller::grant_quick_registration((int) $course->id, $person, null, null, true);
        $this->assertSame('closed', $trusted->status);
        $this->assertSame('closed', access_controller::check_quickreg_gate((int) $course->id, 'x', '10.0.0.1'));
        $this->assertSame($before, $this->footprint());
        // Nothing is offered on the entry page either.
        $offer = local\access_gate::offerable(api::get_effective_policy((int) $course->id), time(), 0);
        $this->assertFalse($offer->temporary || $offer->quick || $offer->guest || $offer->normallogin);
    }
}
