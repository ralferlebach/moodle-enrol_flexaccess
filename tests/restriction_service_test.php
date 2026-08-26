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
 * Integration tests for FlexAccess identity-dependent restrictions via the facade.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Restriction service / facade integration tests.
 *
 * @package    enrol_flexaccess
 */
#[CoversClass(\enrol_flexaccess\local\restriction_service::class)]
final class restriction_service_test extends \advanced_testcase {
    /**
     * Insert a restriction row.
     *
     * @param string $scope Scope level (system/category/instance).
     * @param int $scopeid Scope instance id.
     * @param string $kind Restriction kind (role or cohort).
     * @param int $refid Reference id (role id or cohort id).
     * @param string $effect Effect, either allow or deny.
     * @return void
     */
    private function restrict(string $scope, int $scopeid, string $kind, int $refid, string $effect): void {
        global $DB;
        $DB->insert_record('enrol_flexaccess_restrict', (object) [
            'scope' => $scope, 'scopeid' => $scopeid, 'kind' => $kind, 'refid' => $refid, 'effect' => $effect,
        ]);
    }

    /**
     * No restrictions permits any user.
     */
    public function test_permits_when_no_restrictions(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->assertTrue(\enrol_flexaccess\api::is_user_permitted((int) $course->id, (int) $user->id));
    }

    /**
     * A course-scoped cohort deny blocks members only.
     */
    public function test_cohort_deny(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cohort = $this->getDataGenerator()->create_cohort();
        $member = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $member->id);

        $this->restrict('course', (int) $course->id, 'cohort', (int) $cohort->id, 'deny');

        $this->assertFalse(\enrol_flexaccess\api::is_user_permitted((int) $course->id, (int) $member->id));
        $this->assertTrue(\enrol_flexaccess\api::is_user_permitted((int) $course->id, (int) $outsider->id));
    }

    /**
     * An allow role restriction restricts access to holders of that role.
     */
    public function test_role_allowlist(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);

        $insider = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        role_assign($roleid, $insider->id, $context->id);

        $this->restrict('course', (int) $course->id, 'role', $roleid, 'allow');

        $this->assertTrue(\enrol_flexaccess\api::is_user_permitted((int) $course->id, (int) $insider->id));
        $this->assertFalse(\enrol_flexaccess\api::is_user_permitted((int) $course->id, (int) $outsider->id));
    }

    /**
     * get_effective_policy withdraws FlexAccess methods for a restricted user.
     */
    public function test_effective_policy_withdraws_methods(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        $DB->set_field('enrol_flexaccess_instance', 'allowtemporary', 1, ['enrolid' => $enrolid]);

        $cohort = $this->getDataGenerator()->create_cohort();
        $blocked = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $blocked->id);
        $this->restrict('course', (int) $course->id, 'cohort', (int) $cohort->id, 'deny');

        // Without a user, the assembled policy offers temporary access.
        $this->assertTrue(\enrol_flexaccess\api::get_effective_policy((int) $course->id)->allowtemporary);
        // For the blocked user, FlexAccess methods are withdrawn.
        $this->assertFalse(
            \enrol_flexaccess\api::get_effective_policy((int) $course->id, (int) $blocked->id)->allowtemporary
        );
    }
}
