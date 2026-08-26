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

use PHPUnit\Framework\Attributes\CoversClass;
use enrol_flexaccess\local\enrol_expiry;
use enrol_flexaccess\local\instance_config;

/**
 * Tests for enrolment expiry (suspend / unenrol) and the enrolperiod configuration.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_flexaccess\local\enrol_expiry::class)]
final class enrol_expiry_test extends \advanced_testcase {
    /**
     * Create a course with a FlexAccess instance and enrol a user with a given end time.
     *
     * @param string $expiryaction 'suspend' or 'unenrol'.
     * @param int $timeend Enrolment end time (0 = no end).
     * @return array{0: \stdClass, 1: \stdClass, 2: int} Course, user, enrol instance id.
     */
    private function enrolled_user(string $expiryaction, int $timeend): array {
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        instance_config::save($enrolid, ['allowtemporary' => 1, 'expiryaction' => $expiryaction]);
        $instance = $GLOBALS['DB']->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        $plugin->enrol_user($instance, (int) $user->id, null, time() - DAYSECS, $timeend, ENROL_USER_ACTIVE);
        return [$course, $user, (int) $enrolid];
    }

    /**
     * A due enrolment configured to suspend is suspended, not removed.
     *
     * @return void
     */
    public function test_expiry_suspends(): void {
        global $DB;
        $this->resetAfterTest();
        $now = time();
        [$course, $user, $enrolid] = $this->enrolled_user('suspend', $now - 60);

        $this->assertSame(1, enrol_expiry::process($now));

        $ue = $DB->get_record('user_enrolments', ['enrolid' => $enrolid, 'userid' => $user->id]);
        $this->assertNotFalse($ue);
        $this->assertEquals(ENROL_USER_SUSPENDED, (int) $ue->status);
    }

    /**
     * A due enrolment configured to unenrol removes the enrolment entirely.
     *
     * @return void
     */
    public function test_expiry_unenrols(): void {
        global $DB;
        $this->resetAfterTest();
        $now = time();
        [$course, $user, $enrolid] = $this->enrolled_user('unenrol', $now - 60);

        $this->assertSame(1, enrol_expiry::process($now));
        $this->assertFalse($DB->record_exists('user_enrolments', ['enrolid' => $enrolid, 'userid' => $user->id]));
    }

    /**
     * Enrolments that have not reached their end time (or have none) are left untouched.
     *
     * @return void
     */
    public function test_active_enrolments_are_untouched(): void {
        global $DB;
        $this->resetAfterTest();
        $now = time();
        [, $future, $futureenrol] = $this->enrolled_user('unenrol', $now + WEEKSECS);
        [, $noend, $noendenrol] = $this->enrolled_user('unenrol', 0);

        $this->assertSame(0, enrol_expiry::process($now));
        $this->assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $futureenrol, 'userid' => $future->id]));
        $this->assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $noendenrol, 'userid' => $noend->id]));
    }

    /**
     * The enrolment duration is stored by the instance configuration and drives the end time.
     *
     * @return void
     */
    public function test_enrolperiod_is_saved_and_applied(): void {
        $this->resetAfterTest();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        instance_config::save($enrolid, ['allowtemporary' => 1, 'enrolperiod' => 3600]);

        $config = instance_config::load($enrolid);
        $this->assertEquals(3600, (int) $config->enrolperiod);

        // A new enrolment through the service gets an end time enrolperiod seconds out.
        $now = 1000000;
        $status = local\enrol_service::enrol_with_capacity($enrolid, (int) $this->getDataGenerator()->create_user()->id, $now);
        $this->assertSame('enrolled', $status);
        $ue = $GLOBALS['DB']->get_record('user_enrolments', ['enrolid' => $enrolid]);
        $this->assertEquals($now + 3600, (int) $ue->timeend);
    }
}
