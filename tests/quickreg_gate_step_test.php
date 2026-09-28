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
use enrol_flexaccess\local\access_key_rate;
use enrol_flexaccess\local\gate_pass;
use enrol_flexaccess\local\instance_config;
use enrol_flexaccess\local\quickreg_gate;

/**
 * Tests for the gate-first quick registration (issue auth#9, ACCESSGATE).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_flexaccess\local\access_controller
 * @covers \enrol_flexaccess\local\gate_pass
 */
final class quickreg_gate_step_test extends \advanced_testcase {
    /** Course access password used in the tests. */
    private const SECRET = 'open-sesame';

    /**
     * Skip without auth_flexaccess; configure a password gate.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        if (!$DB->get_manager()->table_exists('auth_flexaccess_account')) {
            $this->markTestSkipped('Requires the auth_flexaccess sibling plugin to be installed.');
        }
        $this->resetAfterTest();
        set_config('requireemailverification', 0, 'auth_flexaccess');
        set_config('allowwidening', 1, 'enrol_flexaccess');
        set_config('quickreggatemode', 'password', 'enrol_flexaccess');
        set_config('quickreggatepasswordhash', quickreg_gate::hash(self::SECRET), 'enrol_flexaccess');
    }

    /**
     * A course with an enabled FlexAccess instance allowing quick registration.
     *
     * @return \stdClass
     */
    private function course(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $enrolid = enrol_get_plugin('flexaccess')->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        instance_config::save($enrolid, ['allowquick' => 1]);
        \cache::make('enrol_flexaccess', 'policy')->purge();
        return $course;
    }

    /**
     * Registration data without any course access password (second step).
     *
     * @param string $email Address.
     * @return \stdClass
     */
    private function person(string $email): \stdClass {
        return (object) ['email' => $email, 'firstname' => 'Gate', 'lastname' => 'First', 'password' => 'Str0ng-Pass!23'];
    }

    /**
     * Number of FlexAccess accounts and enrolments (nothing may be created by the gate step).
     *
     * @return array
     */
    private function footprint(): array {
        global $DB;
        return [
            $DB->count_records('auth_flexaccess_account'),
            $DB->count_records('user_enrolments'),
            $DB->count_records('user'),
        ];
    }

    /**
     * Correct password: pass issued, nothing created; the registration then needs no password.
     *
     * @return void
     */
    public function test_correct_password_then_registration(): void {
        $course = $this->course();
        $before = $this->footprint();
        $this->assertFalse(access_controller::has_quickreg_gate_pass((int) $course->id));
        $this->assertSame('passed', access_controller::check_quickreg_gate((int) $course->id, self::SECRET, '10.0.0.1'));
        $this->assertSame($before, $this->footprint(), 'The gate step must not create anything.');
        $this->assertTrue(access_controller::has_quickreg_gate_pass((int) $course->id));

        $result = access_controller::grant_quick_registration((int) $course->id, $this->person('gate.ok@example.com'));
        $this->assertSame('granted', $result->status);
        // The pass is consumed: it cannot be replayed for a second registration.
        $this->assertFalse(access_controller::has_quickreg_gate_pass((int) $course->id));
        $again = access_controller::grant_quick_registration((int) $course->id, $this->person('gate.two@example.com'));
        $this->assertSame('badgate', $again->status);
    }

    /**
     * Wrong password: no form, no account, no enrolment, no capacity used.
     *
     * @return void
     */
    public function test_wrong_password_creates_nothing(): void {
        $course = $this->course();
        $before = $this->footprint();
        $this->assertSame('badgate', access_controller::check_quickreg_gate((int) $course->id, 'wrong', '10.0.0.2'));
        $this->assertFalse(access_controller::has_quickreg_gate_pass((int) $course->id));
        $this->assertSame($before, $this->footprint());
        $this->assertSame(0, api::get_active_enrolment_count((int) $course->id));
    }

    /**
     * Direct controller calls cannot bypass the gate (no pass, no password).
     *
     * @return void
     */
    public function test_direct_controller_call_cannot_bypass(): void {
        $course = $this->course();
        $before = $this->footprint();
        $result = access_controller::grant_quick_registration((int) $course->id, $this->person('bypass@example.com'));
        $this->assertSame('badgate', $result->status);
        $this->assertSame($before, $this->footprint());
        // API callers that pass the correct password themselves keep working.
        $withpassword = $this->person('api@example.com');
        $withpassword->accesspassword = self::SECRET;
        $this->assertSame('granted', access_controller::grant_quick_registration((int) $course->id, $withpassword)->status);
    }

    /**
     * The pass is bound to course, session, gate secret and time.
     *
     * @return void
     */
    public function test_pass_binding(): void {
        $course = $this->course();
        $other = $this->course();
        $hash = (string) get_config('enrol_flexaccess', 'quickreggatepasswordhash');
        $now = time();
        access_controller::check_quickreg_gate((int) $course->id, self::SECRET, '10.0.0.3', $now);

        // Replay in another course is rejected.
        $this->assertFalse(access_controller::has_quickreg_gate_pass((int) $other->id));
        $replay = access_controller::grant_quick_registration((int) $other->id, $this->person('x@example.com'));
        $this->assertSame('badgate', $replay->status);
        // Other purpose, expiry, changed secret.
        $this->assertFalse(gate_pass::is_valid((int) $course->id, 'otherpurpose', $hash, $now));
        $this->assertFalse(gate_pass::is_valid((int) $course->id, gate_pass::PURPOSE_QUICKREG, $hash, $now + gate_pass::TTL + 1));
        $this->assertFalse(gate_pass::is_valid((int) $course->id, gate_pass::PURPOSE_QUICKREG, quickreg_gate::hash('new'), $now));
        $this->assertTrue(gate_pass::is_valid((int) $course->id, gate_pass::PURPOSE_QUICKREG, $hash, $now));
    }

    /**
     * A pass carried over into a different session is not accepted.
     *
     * @return void
     */
    public function test_pass_bound_to_session(): void {
        global $SESSION;
        $course = $this->course();
        $hash = (string) get_config('enrol_flexaccess', 'quickreggatepasswordhash');
        gate_pass::issue((int) $course->id, gate_pass::PURPOSE_QUICKREG, $hash);
        $this->assertTrue(gate_pass::is_valid((int) $course->id, gate_pass::PURPOSE_QUICKREG, $hash));
        // A pass that was issued in another session carries that session's fingerprint.
        $slot = gate_pass::PURPOSE_QUICKREG . ':' . $course->id;
        $SESSION->enrol_flexaccess_gatepass[$slot]['session'] = sha1('enrol_flexaccess_gatepass|another-session');
        $this->assertFalse(gate_pass::is_valid((int) $course->id, gate_pass::PURPOSE_QUICKREG, $hash));
        // The pass itself holds no secret: neither the clear-text password nor the stored hash.
        $stored = json_encode($SESSION->enrol_flexaccess_gatepass);
        $this->assertStringNotContainsString(self::SECRET, $stored);
        $this->assertStringNotContainsString($hash, $stored);
    }

    /**
     * Failed gate attempts are rate limited per client and course (fail closed while blocked).
     *
     * @return void
     */
    public function test_rate_limited(): void {
        $course = $this->course();
        for ($i = 0; $i < access_key_rate::MAX_ATTEMPTS; $i++) {
            $this->assertSame('badgate', access_controller::check_quickreg_gate((int) $course->id, 'nope', '10.0.0.4'));
        }
        // Even the correct password is refused while blocked.
        $this->assertSame('ratelimited', access_controller::check_quickreg_gate((int) $course->id, self::SECRET, '10.0.0.4'));
        $this->assertFalse(access_controller::has_quickreg_gate_pass((int) $course->id));
        // Another client is unaffected.
        $this->assertSame('passed', access_controller::check_quickreg_gate((int) $course->id, self::SECRET, '10.0.0.5'));
    }

    /**
     * Without a password gate there is no extra step; closed or disallowed courses fail closed.
     *
     * @return void
     */
    public function test_no_gate_and_fail_closed(): void {
        $course = $this->course();
        set_config('quickreggatemode', 'none', 'enrol_flexaccess');
        \cache::make('enrol_flexaccess', 'policy')->purge();
        $this->assertSame('notrequired', access_controller::check_quickreg_gate((int) $course->id, '', '10.0.0.6'));
        $free = access_controller::grant_quick_registration((int) $course->id, $this->person('free@example.com'));
        $this->assertSame('granted', $free->status);

        // Password mode without a configured secret: fail closed, also for the gate step.
        set_config('quickreggatemode', 'password', 'enrol_flexaccess');
        set_config('quickreggatepasswordhash', '', 'enrol_flexaccess');
        \cache::make('enrol_flexaccess', 'policy')->purge();
        $this->assertSame('badgate', access_controller::check_quickreg_gate((int) $course->id, 'anything', '10.0.0.7'));

        $closed = $this->getDataGenerator()->create_course();
        $this->assertSame('notallowed', access_controller::check_quickreg_gate((int) $closed->id, self::SECRET, '10.0.0.8'));
    }

    /**
     * Trusted provisioning (invitation, campaign) needs no course password and no pass.
     *
     * @return void
     */
    public function test_trusted_gate_needs_no_second_challenge(): void {
        $course = $this->course();
        $result = access_controller::grant_quick_registration(
            (int) $course->id,
            $this->person('invited@example.com'),
            null,
            null,
            true
        );
        $this->assertSame('granted', $result->status);
    }
}
