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
use enrol_flexaccess\local\instance_config;

/**
 * Tests for the FlexAccess anonymous-entry gate and instance configuration form persistence.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_flexaccess\api::class)]
final class access_entry_test extends \advanced_testcase {
    /**
     * Create a course with an enabled FlexAccess instance and return [course, enrolid].
     *
     * @param array $config Extended configuration to persist on the instance.
     * @return array{0: \stdClass, 1: int}
     */
    private function course_with_instance(array $config = []): array {
        global $DB;
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        if ($config) {
            instance_config::save($enrolid, $config);
        }
        return [$course, $enrolid];
    }

    /**
     * A course only offers anonymous entry when a method is enabled and the window is open.
     *
     * @return void
     */
    public function test_offers_anonymous_entry(): void {
        $this->resetAfterTest();

        // No anonymous method enabled: not offered.
        [$course1] = $this->course_with_instance(['allownormallogin' => 1]);
        $this->assertFalse(api::offers_anonymous_entry((int) $course1->id));

        // Temporary access enabled: offered.
        [$course2] = $this->course_with_instance(['allowtemporary' => 1]);
        $this->assertTrue(api::offers_anonymous_entry((int) $course2->id));

        // A course without any FlexAccess instance is never offered.
        $bare = $this->getDataGenerator()->create_course();
        $this->assertFalse(api::offers_anonymous_entry((int) $bare->id));
    }

    /**
     * Guest access and normal-login offers follow their policy flags.
     *
     * @return void
     */
    public function test_offers_guest_and_normal_login(): void {
        $this->resetAfterTest();

        // Normal login is on by default and is not tied to the access window.
        [$default] = $this->course_with_instance(['allowtemporary' => 1]);
        $this->assertTrue(api::offers_normal_login((int) $default->id));
        $this->assertFalse(api::offers_guest_access((int) $default->id));

        // Guest enabled in policy but NO core guest enrolment in the course: not offered, because
        // "enter as guest" would fail at the course.
        [$guest] = $this->course_with_instance(['allowguest' => 1, 'allownormallogin' => 0]);
        $this->assertFalse(api::offers_guest_access((int) $guest->id));
        $this->assertFalse(api::offers_normal_login((int) $guest->id));

        // Once a usable core guest enrolment exists, the guest button is offered.
        enrol_get_plugin('guest')->add_instance($guest, ['status' => ENROL_INSTANCE_ENABLED]);
        \cache::make('enrol_flexaccess', 'policy')->purge();
        $this->assertTrue(api::offers_guest_access((int) $guest->id));

        // A course without any FlexAccess instance offers neither.
        $bare = $this->getDataGenerator()->create_course();
        $this->assertFalse(api::offers_guest_access((int) $bare->id));
        $this->assertFalse(api::offers_normal_login((int) $bare->id));
    }

    public function test_offers_magic_login_is_independent_of_credentials_login(): void {
        $this->resetAfterTest();

        // Email-link login on, credentials login off: only the magic method is offered.
        [$magic] = $this->course_with_instance(['allowmagiclogin' => 1, 'allownormallogin' => 0]);
        $this->assertTrue(api::offers_magic_login((int) $magic->id));
        $this->assertFalse(api::offers_normal_login((int) $magic->id));

        // Email-link login off, credentials login on: the reverse.
        [$creds] = $this->course_with_instance(['allowmagiclogin' => 0, 'allownormallogin' => 1]);
        $this->assertFalse(api::offers_magic_login((int) $creds->id));
        $this->assertTrue(api::offers_normal_login((int) $creds->id));
    }

    /**
     * A closed access window suppresses anonymous entry even with a method enabled.
     *
     * @return void
     */
    public function test_window_closes_entry(): void {
        $this->resetAfterTest();
        $now = 1000000;
        [$course] = $this->course_with_instance([
            'allowtemporary' => 1,
            'availablefrom' => $now + DAYSECS,
        ]);
        $this->assertFalse(api::offers_anonymous_entry((int) $course->id, $now));
    }

    /**
     * The instance form fields are persisted and reloaded.
     *
     * @return void
     */
    public function test_instance_config_persists_fields(): void {
        $this->resetAfterTest();
        [, $enrolid] = $this->course_with_instance([
            'allowtemporary' => 1,
            'allowquick' => 1,
            'allowguest' => 0,
            'allownormallogin' => 0,
            'temporarylifetime' => 3600,
            'expiryaction' => 'unenrol',
        ]);
        $config = instance_config::load($enrolid);
        $this->assertSame(1, (int) $config->allowtemporary);
        $this->assertSame(1, (int) $config->allowquick);
        $this->assertSame(0, (int) $config->allowguest);
        $this->assertSame(0, (int) $config->allownormallogin);
        $this->assertSame(3600, (int) $config->temporarylifetime);
        $this->assertSame('unenrol', $config->expiryaction);
    }

    /**
     * Only one FlexAccess enrolment method is allowed per course.
     *
     * @return void
     */
    public function test_one_instance_per_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->course_with_instance(['allowtemporary' => 1]);
        $plugin = enrol_get_plugin('flexaccess');
        $this->assertFalse($plugin->can_add_instance((int) $course->id));

        $fresh = $this->getDataGenerator()->create_course();
        $this->assertTrue($plugin->can_add_instance((int) $fresh->id));
    }
}
