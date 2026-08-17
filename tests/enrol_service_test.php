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
 * Tests for capacity-guarded FlexAccess enrolment.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use enrol_flexaccess\local\enrol_service;
use enrol_flexaccess\local\capacity_service;

/**
 * Enrolment service tests.
 */
final class enrol_service_test extends \advanced_testcase {
    /**
     * Enrolment fills up to the capacity, then rejects further users.
     */
    public function test_capacity_limit_enforced(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED, 'maxparticipants' => 2]);
        $DB->set_field('enrol_flexaccess_instance', 'maxparticipants', 2, ['enrolid' => $enrolid]);

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();

        $this->assertSame('enrolled', enrol_service::enrol_with_capacity($enrolid, $u1->id));
        $this->assertSame('enrolled', enrol_service::enrol_with_capacity($enrolid, $u2->id));
        $this->assertSame('full', enrol_service::enrol_with_capacity($enrolid, $u3->id));
        $this->assertSame(2, capacity_service::count_active_enrolments($enrolid));
    }

    /**
     * Unlimited capacity never rejects.
     */
    public function test_unlimited_capacity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        for ($i = 0; $i < 5; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->assertSame('enrolled', enrol_service::enrol_with_capacity($enrolid, $user->id));
        }
        $this->assertSame(5, capacity_service::count_active_enrolments($enrolid));
    }

    /**
     * A disabled or non-existent instance is reported as not enabled.
     */
    public function test_not_enabled(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $enrolid]);
        $user = $this->getDataGenerator()->create_user();
        $this->assertSame('notenabled', enrol_service::enrol_with_capacity($enrolid, $user->id));
        $this->assertSame('notenabled', enrol_service::enrol_with_capacity(999999, $user->id));
    }

    /**
     * The enrolment end time is derived from the instance enrolment period.
     */
    public function test_enrolment_period_sets_timeend(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        $DB->set_field('enrol_flexaccess_instance', 'enrolperiod', 3600, ['enrolid' => $enrolid]);
        $now = 1000000;
        $user = $this->getDataGenerator()->create_user();

        $this->assertSame('enrolled', enrol_service::enrol_with_capacity($enrolid, $user->id, $now));
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $enrolid, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame($now + 3600, (int) $ue->timeend);
    }
}
