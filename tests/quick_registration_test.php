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
 * Tests for the quick-registration flow (persistent, loginnable account).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_flexaccess\local\access_controller
 */
final class quick_registration_test extends \advanced_testcase {
    /**
     * Skip when the auth_flexaccess sibling plugin is not installed (per-plugin CI).
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;
        if (!$DB->get_manager()->table_exists('auth_flexaccess_account')) {
            $this->markTestSkipped('Requires the auth_flexaccess sibling plugin to be installed.');
        }
    }

    /**
     * Create a course with an enabled FlexAccess instance allowing quick registration.
     *
     * @return \stdClass The course.
     */
    private function course_allowing_quick(): \stdClass {
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        instance_config::save($enrolid, ['allowquick' => 1]);
        return $course;
    }

    /**
     * Quick registration creates a persistent account that is enrolled and can log in again.
     *
     * @return void
     */
    public function test_quick_registration_creates_loginnable_enrolled_account(): void {
        $this->resetAfterTest();
        $course = $this->course_allowing_quick();
        $sink = $this->redirectEmails();

        $result = access_controller::grant_quick_registration((int) $course->id, (object) [
            'email' => 'learner@example.com',
            'firstname' => 'Test',
            'lastname' => 'Learner',
            'password' => 'Str0ng-Pass!23',
        ]);

        // The upgrade to a real account is bound to email activation: access is granted immediately
        // (enrolled, provisional) but a verification link is sent.
        $this->assertSame('verificationsent', $result->status);
        $this->assertTrue(is_enrolled(
            \context_course::instance((int) $course->id),
            \core_user::get_user($result->userid)
        ));

        // Follow the activation link: the provisional account becomes a full, loginnable account.
        \auth_flexaccess\local\mail_worker::run(time());
        $decoded = quoted_printable_decode($sink->get_messages()[0]->body);
        $sink->close();
        preg_match('/token=([A-Za-z0-9]+)/', $decoded, $m);
        $this->assertSame('converted', \auth_flexaccess\api::confirm_persistence($m[1]));

        $auth = get_auth_plugin('flexaccess');
        $this->assertTrue($auth->user_login('learner@example.com', 'Str0ng-Pass!23'));
        $this->assertFalse($auth->user_login('learner@example.com', 'wrong-password'));
        $this->assertFalse(\auth_flexaccess\api::email_available('learner@example.com'));
    }

    /**
     * Quick registration is rate limited per client address once the cap is reached.
     *
     * @return void
     */
    public function test_quick_registration_rate_limited_per_ip(): void {
        $this->resetAfterTest();
        // Focus on the rate limit, not the verification funnel: use the immediate path.
        set_config('requireemailverification', 0, 'auth_flexaccess');
        $course = $this->course_allowing_quick();
        $ip = '198.51.100.7';

        // Fill the per-IP window (30 by default), then the next attempt is refused.
        for ($i = 0; $i < 30; $i++) {
            $result = access_controller::grant_quick_registration((int) $course->id, (object) [
                'email' => "rl{$i}@example.com",
                'firstname' => 'Rate',
                'lastname' => 'Limited',
                'password' => 'Str0ng-Pass!23',
            ], $ip);
            $this->assertSame('granted', $result->status);
        }
        $blocked = access_controller::grant_quick_registration((int) $course->id, (object) [
            'email' => 'blocked@example.com',
            'firstname' => 'Rate',
            'lastname' => 'Limited',
            'password' => 'Str0ng-Pass!23',
        ], $ip);
        $this->assertSame('ratelimited', $blocked->status);

        // A different client address is unaffected.
        $other = access_controller::grant_quick_registration((int) $course->id, (object) [
            'email' => 'otherip@example.com',
            'firstname' => 'Other',
            'lastname' => 'Network',
            'password' => 'Str0ng-Pass!23',
        ], '203.0.113.50');
        $this->assertSame('granted', $other->status);
    }

    /**
     * Quick registration is refused when the policy does not allow it.
     *
     * @return void
     */
    public function test_quick_registration_requires_policy(): void {
        $this->resetAfterTest();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);

        $result = access_controller::grant_quick_registration((int) $course->id, (object) [
            'email' => 'nope@example.com',
            'firstname' => 'No',
            'lastname' => 'Policy',
            'password' => 'Str0ng-Pass!23',
        ]);
        $this->assertSame('notallowed', $result->status);
    }

    /**
     * The password gate blocks a wrong password and admits the correct one.
     *
     * @return void
     */
    public function test_password_gate(): void {
        $this->resetAfterTest();
        set_config('requireemailverification', 0, 'auth_flexaccess');
        set_config('quickreggatemode', 'password', 'enrol_flexaccess');
        set_config('quickreggatepasswordhash', \enrol_flexaccess\local\quickreg_gate::hash('open-sesame'), 'enrol_flexaccess');
        $course = $this->course_allowing_quick();

        $bad = access_controller::grant_quick_registration((int) $course->id, (object) [
            'email' => 'a@example.com', 'firstname' => 'A', 'lastname' => 'B',
            'password' => 'Str0ng-Pass!23', 'accesspassword' => 'wrong',
        ]);
        $this->assertSame('badgate', $bad->status);

        $ok = access_controller::grant_quick_registration((int) $course->id, (object) [
            'email' => 'a@example.com', 'firstname' => 'A', 'lastname' => 'B',
            'password' => 'Str0ng-Pass!23', 'accesspassword' => 'open-sesame',
        ]);
        $this->assertSame('granted', $ok->status);
    }

    /**
     * The domain gate admits allowed domains (including subdomains) and blocks others.
     *
     * @return void
     */
    public function test_domain_gate(): void {
        $this->resetAfterTest();
        set_config('requireemailverification', 0, 'auth_flexaccess');
        set_config('quickreggatemode', 'domain', 'enrol_flexaccess');
        set_config('quickreggatedomains', "university.edu\npartner.org", 'enrol_flexaccess');
        $course = $this->course_allowing_quick();

        $blocked = access_controller::grant_quick_registration((int) $course->id, (object) [
            'email' => 'x@gmail.com', 'firstname' => 'X', 'lastname' => 'Y', 'password' => 'Str0ng-Pass!23',
        ]);
        $this->assertSame('badgate', $blocked->status);

        $allowed = access_controller::grant_quick_registration((int) $course->id, (object) [
            'email' => 'stud@cs.university.edu', 'firstname' => 'X', 'lastname' => 'Y', 'password' => 'Str0ng-Pass!23',
        ]);
        $this->assertSame('granted', $allowed->status);
    }

    /**
     * A course instance gate overrides the system default.
     *
     * @return void
     */
    public function test_instance_gate_overrides_system(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('requireemailverification', 0, 'auth_flexaccess');
        // System default: password gate.
        set_config('quickreggatemode', 'password', 'enrol_flexaccess');
        set_config('quickreggatepasswordhash', \enrol_flexaccess\local\quickreg_gate::hash('sys'), 'enrol_flexaccess');
        $course = $this->course_allowing_quick();
        // Instance override: no gate.
        $enrol = $DB->get_record('enrol', ['enrol' => 'flexaccess', 'courseid' => $course->id], '*', IGNORE_MULTIPLE);
        \enrol_flexaccess\local\instance_config::save((int) $enrol->id, ['allowquick' => 1, 'quickreggatemode' => 'none']);

        $result = access_controller::grant_quick_registration((int) $course->id, (object) [
            'email' => 'z@anywhere.com', 'firstname' => 'Z', 'lastname' => 'Q', 'password' => 'Str0ng-Pass!23',
        ]);
        $this->assertSame('granted', $result->status);
    }

    /**
     * an already-used email is rejected up front, leaving no enrolled orphan account.
     *
     * @return void
     */
    public function test_quick_registration_emailtaken_leaves_no_orphan(): void {
        $this->resetAfterTest();
        set_config('requireemailverification', 0, 'auth_flexaccess');
        $course = $this->course_allowing_quick();
        $context = \context_course::instance((int) $course->id);
        // An existing user already owns this address.
        $this->getDataGenerator()->create_user(['email' => 'taken@example.com']);
        $before = count_enrolled_users($context);

        $result = access_controller::grant_quick_registration((int) $course->id, (object) [
            'email' => 'taken@example.com', 'firstname' => 'A', 'lastname' => 'B',
            'password' => 'Str0ng-Pass!23',
        ]);

        $this->assertSame('emailtaken', $result->status);
        // No account was created and no enrolment was left behind.
        $this->assertSame($before, count_enrolled_users($context));
        $this->assertSame(0, (int) $result->userid);
    }

    /**
     * a trusted caller (campaign/invitation) bypasses the course quick-registration gate.
     *
     * @return void
     */
    public function test_trusted_gate_bypasses_course_gate(): void {
        $this->resetAfterTest();
        set_config('requireemailverification', 0, 'auth_flexaccess');
        set_config('quickreggatemode', 'password', 'enrol_flexaccess');
        set_config('quickreggatepasswordhash', \enrol_flexaccess\local\quickreg_gate::hash('course-secret'), 'enrol_flexaccess');
        $course = $this->course_allowing_quick();

        // Untrusted with no course password fails the gate.
        $blocked = access_controller::grant_quick_registration((int) $course->id, (object) [
            'email' => 'g1@example.com', 'firstname' => 'A', 'lastname' => 'B',
            'password' => 'Str0ng-Pass!23', 'accesspassword' => '',
        ], null, null, false);
        $this->assertSame('badgate', $blocked->status);

        // Trusted caller is admitted despite the empty course password.
        $ok = access_controller::grant_quick_registration((int) $course->id, (object) [
            'email' => 'g2@example.com', 'firstname' => 'A', 'lastname' => 'B',
            'password' => 'Str0ng-Pass!23', 'accesspassword' => '',
        ], null, null, true);
        $this->assertSame('granted', $ok->status);
    }
}
