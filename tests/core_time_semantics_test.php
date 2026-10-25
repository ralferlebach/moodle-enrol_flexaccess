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

use enrol_flexaccess\local\enrol_service;
use enrol_flexaccess\local\enrolment_admin;
use enrol_flexaccess\local\enrolment_effective_state as state;

/**
 * The effective state uses Core's time semantics (enrol_flexaccess #11).
 *
 * Core's enrolled-users queries (get_enrolled_sql() with onlyactive: participants, gradebook, completion,
 * LTI grade service) compare timestart < round(now, -2) < timeend; the access check is_enrolled() compares
 * the exact time. "ok" must mean that BOTH accept the user enrolment.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_flexaccess\local\enrolment_effective_state
 * @covers \enrol_flexaccess\local\enrolment_admin
 */
final class core_time_semantics_test extends \advanced_testcase {
    /** @var \frozen_clock The scenario's frozen Moodle clock: every reference time of a test reads it. */
    private \frozen_clock $clock;

    protected function setUp(): void {
        parent::setUp();
        $this->clock = $this->mock_clock_with_frozen();
    }

    /**
     * Fixed-time cases around the rounding threshold (the examples of the issue and the strict bounds).
     *
     * @return array now, timestart, timeend, expected state, expected access window, expected population window.
     */
    public static function fixed_time_provider(): array {
        return [
            'issue example 1: rounded up, end inside the window' => [1751, 1700, 1780, state::EXPIRED, '', state::EXPIRED],
            'issue example 2: rounded down, start equals R' => [1749, 1700, 0, state::NOT_STARTED, '', state::NOT_STARTED],
            'just before the threshold, start below R' => [1749, 1699, 0, state::OK, '', ''],
            'exactly on the threshold (rounds up)' => [1750, 1750, 0, state::OK, '', ''],
            'just after the threshold, start below R' => [1751, 1799, 0, state::NOT_STARTED, state::NOT_STARTED, ''],
            'start equals now exactly, R above' => [1751, 1751, 0, state::OK, '', ''],
            'start equals now exactly, R below' => [1749, 1749, 0, state::NOT_STARTED, '', state::NOT_STARTED],
            'end equals R (strict)' => [1749, 0, 1700, state::EXPIRED, state::EXPIRED, state::EXPIRED],
            'end one second after R' => [1749, 0, 1701, state::EXPIRED, state::EXPIRED, ''],
            'end after both' => [1749, 0, 1801, state::OK, '', ''],
            'end between now and R' => [1751, 0, 1790, state::EXPIRED, '', state::EXPIRED],
            'open-ended, started long ago' => [1751, 0, 0, state::OK, '', ''],
        ];
    }

    /**
     * The classification follows both Core semantics at fixed times.
     *
     * @dataProvider fixed_time_provider
     * @param int $now Exact time.
     * @param int $timestart Start.
     * @param int $timeend End.
     * @param string $expected Overall state.
     * @param string $access Expected access window.
     * @param string $population Expected population window.
     */
    public function test_fixed_time_boundaries(
        int $now,
        int $timestart,
        int $timeend,
        string $expected,
        string $access,
        string $population
    ): void {
        $ue = (object) ['status' => ENROL_USER_ACTIVE, 'timestart' => $timestart, 'timeend' => $timeend];
        $problems = state::classify(true, 1, true, $ue, true, $now);
        $this->assertSame($expected, $problems ? $problems[0] : state::OK);
        $this->assertSame($access, state::time_window($ue, $now, false));
        $this->assertSame($population, state::time_window($ue, $now, true));
    }

    /**
     * Windows relative to the current time and its rounding, for the comparison with Core itself.
     *
     * @return array Offsets: [start relative to, start offset, end relative to, end offset]; relative to
     *     'R' (round(now, -2)), 'now' or 'zero' (the value 0).
     */
    public static function core_case_provider(): array {
        return [
            'open-ended, started long ago' => ['zero', 0, 'zero', 0],
            'start one second below R' => ['R', -1, 'zero', 0],
            'start equals R' => ['R', 0, 'zero', 0],
            'start one second above R' => ['R', 1, 'zero', 0],
            'start equals now' => ['now', 0, 'zero', 0],
            'start one second before now' => ['now', -1, 'zero', 0],
            'end equals R' => ['zero', 0, 'R', 0],
            'end one second above R' => ['zero', 0, 'R', 1],
            'end one second below R' => ['zero', 0, 'R', -1],
            'end equals now' => ['zero', 0, 'now', 0],
            'end one second after now' => ['zero', 0, 'now', 1],
            'end in an hour' => ['zero', 0, 'now', HOURSECS],
            'starts in an hour' => ['now', HOURSECS, 'zero', 0],
        ];
    }

    /**
     * "ok" exactly when Core's enrolled-users query AND its access check include the user; each window
     * equals the respective Core result.
     *
     * @dataProvider core_case_provider
     * @param string $startrel Start reference.
     * @param int $startoffset Start offset.
     * @param string $endrel End reference.
     * @param int $endoffset End offset.
     */
    public function test_state_matches_core(string $startrel, int $startoffset, string $endrel, int $endoffset): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance((int) $course->id);
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $this->assertTrue(enrol_service::admin_enrol((int) $course->id, $userid));
        $ueid = (int) $DB->get_field_sql(
            "SELECT ue.id FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :u AND e.courseid = :c AND e.enrol = 'flexaccess'",
            ['u' => $userid, 'c' => $course->id]
        );
        // Core reads the wall clock itself: align the frozen clock with it, and repeat if the second changed
        // during the comparison (Core rounds to 100 seconds).
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->clock->set_to($this->core_wall_time());
            $now = $this->clock->time();
            $refs = ['zero' => 0, 'now' => $now, 'R' => state::core_now($now)];
            $start = $startrel === 'zero' ? 0 : $refs[$startrel] + $startoffset;
            $end = $endrel === 'zero' ? 0 : $refs[$endrel] + $endoffset;
            $DB->update_record('user_enrolments', (object) ['id' => $ueid, 'timestart' => $start, 'timeend' => $end]);

            $evaluated = state::evaluate($userid, (int) $course->id, $now);
            $population = array_key_exists($userid, get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true));
            $access = is_enrolled($context, $userid, '', true);
            $bulk = enrolment_admin::get_enrolments_for_users([$userid], $now)[$userid][0]->effective;
            if ($this->core_wall_time() === $now) {
                break;
            }
        }
        $this->assertSame($population, $evaluated->population === '', 'population window');
        $this->assertSame($access, $evaluated->access === '', 'access window');
        $this->assertSame($population && $access, $evaluated->state === state::OK, 'overall state');
        $this->assertSame($evaluated->state, $bulk, 'bulk and single evaluation agree');
    }

    /**
     * Reactivation treats an end inside the rounding window as passed, so the reactivated enrolment is in
     * Core's lists at once; a start inside the window is not something reactivation fixes.
     */
    public function test_reactivation_closes_the_rounding_window(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $this->assertTrue(enrol_service::admin_enrol((int) $course->id, $userid));
        $ue = $DB->get_record_sql(
            "SELECT ue.* FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :u AND e.courseid = :c AND e.enrol = 'flexaccess'",
            ['u' => $userid, 'c' => $course->id]
        );
        // Fixed instants: now = 1751 rounds to 1800, an end at 1790 is open for is_enrolled() only.
        $now = 2000000051;
        $DB->update_record('user_enrolments', (object) [
            'id' => $ue->id,
            'status' => ENROL_USER_SUSPENDED,
            'timestart' => 0,
            'timeend' => $now + 39,
        ]);
        $result = enrolment_admin::reactivate((int) $ue->id, (int) $course->id, $now);
        $this->assertSame(enrolment_admin::REACTIVATED, $result->outcome);
        $this->assertSame(state::OK, $result->effective->state);
        $this->assertNotSame($now + 39, (int) $DB->get_field('user_enrolments', 'timeend', ['id' => $ue->id]));

        // A start inside the window (now = ...49 rounds down to ...00, start at now): is_enrolled() accepts
        // it, Core's lists do not include the user yet, so the state is not ok.
        $later = 2000000149;
        $DB->update_record('user_enrolments', (object) ['id' => $ue->id, 'timestart' => $later, 'timeend' => 0]);
        $evaluated = state::evaluate($userid, (int) $course->id, $later);
        $this->assertSame(state::NOT_STARTED, $evaluated->state);
        $this->assertSame('', $evaluated->access, 'is_enrolled() would accept it');
        $this->assertSame(state::NOT_STARTED, $evaluated->population);
    }

    /**
     * The second Moodle core reads for its enrolment windows (`round(time(), -2)` in the enrolled-users queries,
     * `time()` in is_enrolled()): Core does not use the Clock API there, so the comparison needs the wall clock.
     *
     * @return int
     */
    private function core_wall_time(): int {
        return time(); // Core's own time source (clock-exception).
    }
}
