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
use auth_flexaccess\local\account_state;

/**
 * Tests that a temporary user can be persisted while keeping the same user id and enrolment.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \auth_flexaccess\api
 */
final class persistence_test extends \advanced_testcase {
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
     * Temporary access, once persisted, keeps the same user id, enrolment and becomes loginnable.
     *
     * @return void
     */
    public function test_temporary_access_persists_and_keeps_enrolment(): void {
        $this->resetAfterTest();

        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        instance_config::save($enrolid, ['allowtemporary' => 1]);

        // Anonymous temporary entry: a temporary, enrolled account.
        $granted = access_controller::grant_temporary_access((int) $course->id);
        $this->assertSame('granted', $granted->status);
        $userid = (int) $granted->userid;
        $context = \context_course::instance((int) $course->id);
        $this->assertTrue(is_enrolled($context, \core_user::get_user($userid)));
        $this->assertTrue(\auth_flexaccess\local\account_service::is_temporary($userid));

        // The user makes the account permanent.
        $status = \auth_flexaccess\api::persist_temporary_user(
            $userid,
            'kept@example.com',
            'Kept',
            'Learner',
            'Str0ng-Pass!23'
        );
        $this->assertSame('converted', $status);

        // Same user id: still enrolled, no longer temporary, and now able to log in.
        $this->assertFalse(\auth_flexaccess\local\account_service::is_temporary($userid));
        $this->assertTrue(is_enrolled($context, \core_user::get_user($userid)));

        $refreshed = \core_user::get_user($userid);
        $this->assertSame('kept@example.com', $refreshed->email);
        $this->assertSame('kept@example.com', $refreshed->username);
        $this->assertEquals(0, (int) $refreshed->emailstop);

        $auth = get_auth_plugin('flexaccess');
        $this->assertTrue($auth->user_login('kept@example.com', 'Str0ng-Pass!23'));
        $this->assertFalse(\auth_flexaccess\api::email_available('kept@example.com'));
    }

    /**
     * Persistence is refused for an account that is not a temporary FlexAccess account.
     *
     * @return void
     */
    public function test_persist_rejects_non_temporary(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $status = \auth_flexaccess\api::persist_temporary_user(
            (int) $user->id,
            'other@example.com',
            'Other',
            'Person',
            'Str0ng-Pass!23'
        );
        $this->assertSame('nottemporary', $status);
    }

    /**
     * Create a course allowing temporary access and grant a temporary, enrolled account.
     *
     * @return array{0: int, 1: \context_course}
     */
    private function granted_temporary_user(): array {
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        instance_config::save($enrolid, ['allowtemporary' => 1]);
        $granted = access_controller::grant_temporary_access((int) $course->id);
        $this->assertSame('granted', $granted->status);
        return [(int) $granted->userid, \context_course::instance((int) $course->id)];
    }

    /**
     * With verification enabled (the default), a link is emailed and the account only converts on confirm.
     *
     * @return void
     */
    public function test_persistence_with_verification_sends_link_and_converts_on_confirm(): void {
        $this->resetAfterTest();
        set_config('requireemailverification', 1, 'auth_flexaccess');
        [$userid, $context] = $this->granted_temporary_user();

        $sink = $this->redirectEmails();
        $status = \auth_flexaccess\api::request_persistence(
            $userid,
            'verify@example.com',
            'Ver',
            'Ified',
            'Str0ng-Pass!23'
        );
        // The verification mail goes through the queue; the worker delivers it.
        $this->assertSame(0, $sink->count());
        \auth_flexaccess\local\mail_worker::run(time());
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertSame('verificationsent', $status);
        // Still temporary and not yet loginnable until the email is confirmed.
        $this->assertTrue(\auth_flexaccess\local\account_service::is_temporary($userid));
        $this->assertFalse(get_auth_plugin('flexaccess')->user_login('verify@example.com', 'Str0ng-Pass!23'));

        $this->assertCount(1, $messages);
        $this->assertSame('verify@example.com', $messages[0]->to);
        // The captured message is a quoted-printable MIME body; decode it to read the link.
        $decoded = quoted_printable_decode($messages[0]->body);
        $this->assertMatchesRegularExpression('/token=[A-Za-z0-9]{64}/', $decoded);
        preg_match('/token=([A-Za-z0-9]+)/', $decoded, $m);

        // Confirm: now permanent, still enrolled (same id), and loginnable.
        $this->assertSame('converted', \auth_flexaccess\api::confirm_persistence($m[1]));
        $this->assertFalse(\auth_flexaccess\local\account_service::is_temporary($userid));
        $this->assertTrue(is_enrolled($context, \core_user::get_user($userid)));
        $this->assertTrue(get_auth_plugin('flexaccess')->user_login('verify@example.com', 'Str0ng-Pass!23'));

        // The single-use link cannot be replayed.
        $this->assertSame('invalid', \auth_flexaccess\api::confirm_persistence($m[1]));
    }

    /**
     * With verification disabled, request_persistence converts immediately.
     *
     * @return void
     */
    public function test_persistence_without_verification_converts_immediately(): void {
        $this->resetAfterTest();
        set_config('requireemailverification', 0, 'auth_flexaccess');
        [$userid, $context] = $this->granted_temporary_user();

        $status = \auth_flexaccess\api::request_persistence(
            $userid,
            'now@example.com',
            'Now',
            'User',
            'Str0ng-Pass!23'
        );
        $this->assertSame('converted', $status);
        $this->assertFalse(\auth_flexaccess\local\account_service::is_temporary($userid));
        $this->assertTrue(is_enrolled($context, \core_user::get_user($userid)));
        $this->assertTrue(get_auth_plugin('flexaccess')->user_login('now@example.com', 'Str0ng-Pass!23'));
    }

    /**
     * SEC-03: a persistence token cannot revive a temporary account that has already expired.
     *
     * @return void
     */
    public function test_expired_account_cannot_be_persisted(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('requireemailverification', 1, 'auth_flexaccess');
        [$userid] = $this->granted_temporary_user();

        $sink = $this->redirectEmails();
        \auth_flexaccess\api::request_persistence($userid, 'late@example.com', 'La', 'Te', 'Str0ng-Pass!23');
        \auth_flexaccess\local\mail_worker::run(time());
        $messages = $sink->get_messages();
        $sink->close();
        preg_match('/token=([A-Za-z0-9]+)/', quoted_printable_decode($messages[0]->body), $m);

        // The temporary account expires before the link is opened.
        $DB->set_field(
            'auth_flexaccess_account',
            'accountstate',
            account_state::EXPIRED,
            ['userid' => $userid]
        );

        $this->assertSame('expired', \auth_flexaccess\api::confirm_persistence($m[1]));
        $this->assertFalse(get_auth_plugin('flexaccess')->user_login('late@example.com', 'Str0ng-Pass!23'));
    }
}
