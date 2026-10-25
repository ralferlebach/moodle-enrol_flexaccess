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
 * Time decisions read Moodle's clock (issue #14).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use enrol_flexaccess\local\access_controller;
use enrol_flexaccess\local\access_key_rate;
use enrol_flexaccess\local\enrol_time;
use enrol_flexaccess\local\enrolment_effective_state as state;
use enrol_flexaccess\local\instance_config;

/**
 * Every decision without an explicit $now uses \core\clock; an explicit $now still wins.
 *
 * @package    enrol_flexaccess
 * @covers \enrol_flexaccess\local\access_controller
 * @covers \enrol_flexaccess\local\enrol_expiry
 * @covers \enrol_flexaccess\local\capacity_service
 * @covers \enrol_flexaccess\local\access_key_rate
 * @covers \enrol_flexaccess\local\enrol_time
 * @covers \enrol_flexaccess\local\enrolment_effective_state
 */
final class clock_test extends \advanced_testcase {
    /** Reference time T used by the boundary tests (2033-05-18). */
    private const T = 2000000000;

    /**
     * A course whose FlexAccess method offers temporary access.
     *
     * @param array $config Additional instance configuration.
     * @return array [courseid, enrolid]
     */
    private function course(array $config = []): array {
        set_config('allowwidening', 1, 'enrol_flexaccess');
        // Entry flows run only while the enrolment and the authentication plugin are both enabled.
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        set_config('auth', 'flexaccess');
        \core\plugin_manager::reset_caches();
        $course = $this->getDataGenerator()->create_course();
        $enrolid = enrol_get_plugin('flexaccess')->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        instance_config::save($enrolid, ['allowtemporary' => 1, 'temporarylifetime' => DAYSECS] + $config);
        return [(int) $course->id, (int) $enrolid];
    }

    /**
     * Access window combinations around their boundaries.
     *
     * @return array
     */
    public static function window_provider(): array {
        $t = self::T;
        return [
            'no window' => [0, 0, $t, 'granted'],
            'from: T-1' => [$t, 0, $t - 1, 'closed'],
            'from: T (inclusive)' => [$t, 0, $t, 'granted'],
            'from: T+1' => [$t, 0, $t + 1, 'granted'],
            'until: T-1' => [0, $t, $t - 1, 'granted'],
            'until: T (exclusive)' => [0, $t, $t, 'closed'],
            'until: T+1' => [0, $t, $t + 1, 'closed'],
            'both: before start' => [$t, $t + 100, $t - 1, 'closed'],
            'both: start' => [$t, $t + 100, $t, 'granted'],
            'both: start+1' => [$t, $t + 100, $t + 1, 'granted'],
            'both: end-1' => [$t, $t + 100, $t + 99, 'granted'],
            'both: end' => [$t, $t + 100, $t + 100, 'closed'],
            'both: end+1' => [$t, $t + 100, $t + 101, 'closed'],
        ];
    }

    /**
     * The access window is evaluated at the clock's time: from inclusive, until exclusive, 0 unlimited.
     *
     * @dataProvider window_provider
     * @param int $from availablefrom.
     * @param int $until availableuntil.
     * @param int $now Frozen clock time.
     * @param string $expected Grant status.
     */
    public function test_window_boundaries_follow_clock(int $from, int $until, int $now, string $expected): void {
        $this->resetAfterTest();
        $this->redirectEmails();
        [$courseid] = $this->course(['availablefrom' => $from, 'availableuntil' => $until]);
        $this->mock_clock_with_frozen($now);
        $this->assertSame($expected, access_controller::grant_temporary_access($courseid)->status);
        $this->assertSame($expected === 'granted', api::offers_anonymous_entry($courseid));
    }

    /**
     * An explicit $now takes precedence over the clock.
     */
    public function test_explicit_now_wins(): void {
        $this->resetAfterTest();
        $this->redirectEmails();
        [$courseid] = $this->course(['availablefrom' => self::T, 'availableuntil' => self::T + 100]);
        $this->mock_clock_with_frozen(self::T + 500);
        $this->assertSame('closed', access_controller::grant_temporary_access($courseid)->status);
        $this->assertSame('granted', access_controller::grant_temporary_access($courseid, self::T + 50)->status);
    }

    /**
     * One grant uses one reference time for the account, the enrolment start and its end, even when
     * every clock read returns a later second.
     */
    public function test_grant_uses_one_reference_time(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEmails();
        [$courseid, $enrolid] = $this->course(['enrolperiod' => 3 * DAYSECS]);
        $this->mock_clock_with_incrementing(self::T);
        $result = access_controller::grant_temporary_access($courseid);
        $this->assertSame('granted', $result->status);

        $account = $DB->get_record('auth_flexaccess_account', ['userid' => $result->userid], '*', MUST_EXIST);
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $enrolid, 'userid' => $result->userid], '*', MUST_EXIST);
        $reference = (int) $account->timecreated;
        $this->assertGreaterThanOrEqual(self::T, $reference);
        $this->assertSame($reference + DAYSECS, (int) $account->timeexpires);
        $this->assertSame(enrol_time::immediate_start($reference), (int) $ue->timestart);
        $this->assertSame($reference + 3 * DAYSECS, (int) $ue->timeend);
    }

    /**
     * Capacity counts an enrolment until its end; the boundary follows the clock.
     */
    public function test_capacity_boundary_follows_clock(): void {
        $this->resetAfterTest();
        $this->redirectEmails();
        [$courseid, $enrolid] = $this->course(['maxparticipants' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->get_instance($enrolid);
        enrol_get_plugin('flexaccess')->enrol_user($instance, (int) $user->id, null, self::T - DAYSECS, self::T);

        $clock = $this->mock_clock_with_frozen(self::T - 1);
        $this->assertSame(1, local\capacity_service::count_active_enrolments($enrolid));
        $this->assertSame('full', access_controller::grant_temporary_access($courseid)->status);
        $clock->set_to(self::T);
        $this->assertSame(0, local\capacity_service::count_active_enrolments($enrolid));
        $this->assertSame('granted', access_controller::grant_temporary_access($courseid)->status);
    }

    /**
     * The expiry task acts on enrolments whose end has been reached by the clock, not before.
     */
    public function test_expiry_task_follows_clock(): void {
        global $DB;
        $this->resetAfterTest();
        [, $enrolid] = $this->course(['expiryaction' => 'suspend']);
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->get_instance($enrolid);
        enrol_get_plugin('flexaccess')->enrol_user($instance, (int) $user->id, null, self::T - DAYSECS, self::T);
        $status = fn(): int => (int) $DB->get_field('user_enrolments', 'status', ['enrolid' => $enrolid, 'userid' => $user->id]);

        $clock = $this->mock_clock_with_frozen(self::T - 1);
        $this->run_expiry_task();
        $this->assertSame(ENROL_USER_ACTIVE, $status());
        $clock->set_to(self::T);
        $this->run_expiry_task();
        $this->assertSame(ENROL_USER_SUSPENDED, $status());
    }

    /**
     * The access-key failure counter expires after its window, measured by the clock.
     */
    public function test_access_key_rate_window_follows_clock(): void {
        $this->resetAfterTest();
        $id = access_key_rate::identifier('192.0.2.7', 42);
        $clock = $this->mock_clock_with_frozen(self::T);
        for ($i = 0; $i < access_key_rate::MAX_ATTEMPTS; $i++) {
            access_key_rate::record_failure($id);
        }
        $this->assertTrue(access_key_rate::is_blocked($id));
        $clock->set_to(self::T + access_key_rate::WINDOW);
        $this->assertTrue(access_key_rate::is_blocked($id), 'Still blocked at the end of the window.');
        $clock->set_to(self::T + access_key_rate::WINDOW + 1);
        $this->assertFalse(access_key_rate::is_blocked($id));
    }

    /**
     * Administrative enrolment and its effective state use the clock; Core's active-only lists (which
     * read the wall clock, rounded to 100 s) include the participant at once.
     */
    public function test_admin_enrol_with_clock_is_active_in_core(): void {
        $this->resetAfterTest();
        [$courseid, $enrolid] = $this->course(['enrolperiod' => DAYSECS]);
        $user = $this->getDataGenerator()->create_user();
        // Frozen at the real current time: Core's own active-only comparison uses time().
        $clock = $this->mock_clock_with_frozen();
        $this->assertTrue(local\enrol_service::admin_enrol($courseid, (int) $user->id));
        $this->assertSame(state::OK, api::effective_enrolment_state((int) $user->id, $courseid)->state);
        $active = get_enrolled_users(\context_course::instance($courseid), '', 0, 'u.id', null, 0, 0, true);
        $this->assertArrayHasKey((int) $user->id, $active);
        // One day later (clock only), the FlexAccess end has been reached.
        $clock->bump(DAYSECS);
        $this->assertSame(state::EXPIRED, api::effective_enrolment_state((int) $user->id, $courseid)->state);
        $this->assertNotEmpty($enrolid);
    }

    /**
     * The enrol instance record.
     *
     * @param int $enrolid Enrol id.
     * @return \stdClass
     */
    private function get_instance(int $enrolid): \stdClass {
        global $DB;
        return $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
    }

    /**
     * Run the scheduled expiry task.
     *
     * @return void
     */
    private function run_expiry_task(): void {
        ob_start();
        (new task\expire_enrolments())->execute();
        ob_end_clean();
    }
}
