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
 * Tests for the FlexAccess temporary-access controller.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use PHPUnit\Framework\Attributes\CoversClass;
use enrol_flexaccess\local\access_controller;

/**
 * Access controller tests.
 *
 * @package    enrol_flexaccess
 */
#[CoversClass(\enrol_flexaccess\local\access_controller::class)]
final class access_controller_test extends \advanced_testcase {
    /**
     * Skip when the required sibling plugin is not installed (per-plugin CI).
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
     * Create a course with an enabled FlexAccess instance that offers temporary access.
     *
     * @param int $max Maximum participants (0 = unlimited).
     * @param int $from Window start.
     * @param int $until Window end.
     * @return \stdClass Course record.
     */
    private function course_with_instance(int $max = 0, int $from = 0, int $until = 0): \stdClass {
        global $DB;
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        $DB->set_field('enrol_flexaccess_instance', 'allowtemporary', 1, ['enrolid' => $enrolid]);
        $DB->set_field('enrol_flexaccess_instance', 'temporarylifetime', DAYSECS, ['enrolid' => $enrolid]);
        if ($max > 0) {
            $DB->set_field('enrol_flexaccess_instance', 'maxparticipants', $max, ['enrolid' => $enrolid]);
        }
        if ($from > 0 || $until > 0) {
            $DB->set_field('enrol_flexaccess_instance', 'availablefrom', $from, ['enrolid' => $enrolid]);
            $DB->set_field('enrol_flexaccess_instance', 'availableuntil', $until, ['enrolid' => $enrolid]);
        }
        return $course;
    }

    /**
     * A visitor is granted temporary access: a user is created, enrolled and followed up.
     */
    public function test_grant_success(): void {
        global $DB;
        $this->resetAfterTest();
        $sink = $this->redirectEmails();
        $now = 1000000;
        $course = $this->course_with_instance();

        $result = access_controller::grant_temporary_access((int) $course->id, $now);
        $this->assertSame('granted', $result->status);
        $this->assertGreaterThan(0, $result->userid);
        $this->assertSame(
            \auth_flexaccess\local\account_type::TEMPORARY_USER,
            \auth_flexaccess\api::classify_user($result->userid)
        );
        $this->assertTrue($DB->record_exists(
            'user_enrolments',
            ['enrolid' => $result->enrolid, 'userid' => $result->userid]
        ));
        // Granting temporary access no longer enqueues a follow-up mail (the persistence follow-up
        // path was removed; persistence is now self-service via persist.php).
        $this->assertEquals(0, $DB->count_records(
            'auth_flexaccess_mailqueue',
            ['userid' => $result->userid, 'status' => 'queued']
        ));
        $sink->close();
    }

    /**
     * Access outside the window is refused before creating anything.
     */
    public function test_closed_window(): void {
        global $DB;
        $this->resetAfterTest();
        $now = 5000;
        $course = $this->course_with_instance(0, 1000, 2000);
        $before = $DB->count_records('user');
        $result = access_controller::grant_temporary_access((int) $course->id, $now);
        $this->assertSame('closed', $result->status);
        $this->assertSame($before, $DB->count_records('user'));
    }

    /**
     * A full instance refuses further grants without orphaning an account.
     */
    public function test_full_capacity(): void {
        global $DB;
        $this->resetAfterTest();
        $sink = $this->redirectEmails();
        $now = 1000000;
        $course = $this->course_with_instance(1);
        $first = access_controller::grant_temporary_access((int) $course->id, $now);
        $this->assertSame('granted', $first->status);

        $accountsbefore = $DB->count_records('auth_flexaccess_account');
        $usersbefore = $DB->count_records('user');
        $second = access_controller::grant_temporary_access((int) $course->id, $now);
        $this->assertSame('full', $second->status);
        $this->assertSame(0, (int) $second->userid);
        // The refused grant must not create an account (or a user) that is never enrolled.
        $this->assertSame($accountsbefore, $DB->count_records('auth_flexaccess_account'));
        $this->assertSame($usersbefore, $DB->count_records('user'));
        $sink->close();
    }

    /**
     * Anonymous temporary creation is rate limited per client address (atomic, independent of key).
     *
     * @return void
     */
    public function test_temporary_creation_rate_limited_per_ip(): void {
        $this->resetAfterTest();
        set_config('tempmaxperip', 3, 'enrol_flexaccess');
        set_config('tempwindow', 600, 'enrol_flexaccess');
        $sink = $this->redirectEmails();
        $now = 1000000;
        $course = $this->course_with_instance(0);
        $ip = '198.51.100.20';

        for ($i = 0; $i < 3; $i++) {
            $r = access_controller::grant_temporary_access((int) $course->id, $now, null, $ip);
            $this->assertSame('granted', $r->status);
        }
        $blocked = access_controller::grant_temporary_access((int) $course->id, $now, null, $ip);
        $this->assertSame('ratelimited', $blocked->status);

        // A different address is unaffected.
        $other = access_controller::grant_temporary_access((int) $course->id, $now, null, '203.0.113.44');
        $this->assertSame('granted', $other->status);
        $sink->close();
    }

    /**
     * The site-wide circuit breaker blocks creation regardless of client address once tripped.
     *
     * @return void
     */
    public function test_temporary_creation_site_circuit_breaker(): void {
        $this->resetAfterTest();
        set_config('tempmaxperip', 1000, 'enrol_flexaccess');
        set_config('tempsitemax', 2, 'enrol_flexaccess');
        set_config('tempsitewindow', 3600, 'enrol_flexaccess');
        $sink = $this->redirectEmails();
        $now = 1000000;
        $course = $this->course_with_instance(0);

        $this->assertSame('granted', access_controller::grant_temporary_access((int) $course->id, $now, null, '10.0.0.1')->status);
        $this->assertSame('granted', access_controller::grant_temporary_access((int) $course->id, $now, null, '10.0.0.2')->status);
        // Third creation from a fresh address is still blocked by the site breaker.
        $third = access_controller::grant_temporary_access((int) $course->id, $now, null, '10.0.0.3');
        $this->assertSame('ratelimited', $third->status);
        $sink->close();
    }
}
