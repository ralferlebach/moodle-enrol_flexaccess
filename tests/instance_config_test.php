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
 * Tests for FlexAccess enrol instance-config persistence.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use enrol_flexaccess\local\instance_config;

/**
 * Instance-config persistence tests, including the patch contract (issue #13).
 *
 * @package    enrol_flexaccess
 * @covers \enrol_flexaccess\local\instance_config
 * @covers \enrol_flexaccess_plugin
 */
final class instance_config_test extends \advanced_testcase {
    /**
     * Adding an instance persists the access window and capacity; delete removes it.
     */
    public function test_add_update_delete_persistence(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_flexaccess_plugin $plugin */
        $plugin = enrol_get_plugin('flexaccess');

        $enrolid = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'availablefrom' => 1000,
            'availableuntil' => 2000,
            'maxparticipants' => 30,
        ]);

        $row = instance_config::load($enrolid);
        $this->assertNotNull($row);
        $this->assertSame(1000, (int) $row->availablefrom);
        $this->assertSame(2000, (int) $row->availableuntil);
        $this->assertSame(30, (int) $row->maxparticipants);

        // Update.
        $instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        $plugin->update_instance($instance, (object) [
            'availablefrom' => 0,
            'availableuntil' => 0,
            'maxparticipants' => 0,
            'roleid' => $instance->roleid,
        ]);
        $row = instance_config::load($enrolid);
        $this->assertSame(0, (int) $row->availablefrom);
        $this->assertSame(0, (int) $row->maxparticipants);
        // Exactly one extension row per instance.
        $this->assertEquals(1, $DB->count_records('enrol_flexaccess_instance', ['enrolid' => $enrolid]));

        // Delete.
        $plugin->delete_instance($instance);
        $this->assertNull(instance_config::load($enrolid));
    }

    /**
     * A fully configured instance (every contract field off its default, both secrets set).
     *
     * @return int Enrol instance id.
     */
    private function full_instance(): int {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $course = $this->getDataGenerator()->create_course();
        return enrol_get_plugin('flexaccess')->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'availablefrom' => 1893456000,
            'availableuntil' => 1896134400,
            'maxparticipants' => 17,
            'allowtemporary' => 1,
            'allowquick' => 1,
            'allowguest' => 1,
            'allownormallogin' => 0,
            'allowmagiclogin' => 0,
            'temporarylifetime' => 3 * HOURSECS,
            'enrolperiod' => 5 * DAYSECS,
            'expiryaction' => 'unenrol',
            'temporaryaccesskeymode' => 'course',
            'temporaryaccesskey' => 'KEY-1',
            'participantlistaccess' => 'hide',
            'quickreggatemode' => 'password',
            'quickreggatepassword' => 'GATE-1',
            'quickreggatedomains' => 'example.org',
        ]);
    }

    /**
     * The stored row without bookkeeping columns.
     *
     * @param int $enrolid Enrol instance id.
     * @return array
     */
    private function row(int $enrolid): array {
        $row = (array) instance_config::load($enrolid);
        unset($row['timemodified']);
        return $row;
    }

    /**
     * Changing only one flag through the API keeps every other value, including the secrets.
     */
    public function test_patch_one_flag_keeps_everything_else(): void {
        $enrolid = $this->full_instance();
        $before = $this->row($enrolid);
        $this->assertNotEmpty($before['temporaryaccesskeyhash']);
        $this->assertNotEmpty($before['quickreggatepasswordhash']);

        $this->assertTrue(instance_config::save($enrolid, ['allowquick' => 0]));
        $this->assertEquals(['allowquick' => 0] + $before, $this->row($enrolid));
    }

    /**
     * Patching the capacity to 0 (unlimited) keeps the window and everything else.
     */
    public function test_patch_capacity_to_unlimited_keeps_rest(): void {
        $enrolid = $this->full_instance();
        $before = $this->row($enrolid);
        instance_config::save($enrolid, ['maxparticipants' => 0]);
        $this->assertEquals(['maxparticipants' => 0] + $before, $this->row($enrolid));
    }

    /**
     * Clearing one window bound with an explicit 0 keeps the other bound.
     */
    public function test_patch_one_bound_to_zero_keeps_other(): void {
        $enrolid = $this->full_instance();
        $before = $this->row($enrolid);
        instance_config::save($enrolid, ['availablefrom' => 0]);
        $this->assertEquals(['availablefrom' => 0] + $before, $this->row($enrolid));
        instance_config::save($enrolid, ['availableuntil' => 0]);
        $this->assertEquals(['availablefrom' => 0, 'availableuntil' => 0] + $before, $this->row($enrolid));
    }

    /**
     * Through update_instance: a flag missing from the data is kept, an unticked form checkbox is cleared.
     */
    public function test_missing_flag_differs_from_unticked_form_checkbox(): void {
        global $DB;
        $enrolid = $this->full_instance();
        $plugin = enrol_get_plugin('flexaccess');
        $instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);

        // API patch without allowtemporary: kept.
        $plugin->update_instance($instance, (object) ['maxparticipants' => 9]);
        $this->assertSame(1, (int) instance_config::load($enrolid)->allowtemporary);
        $this->assertSame(9, (int) instance_config::load($enrolid)->maxparticipants);

        // Form submission: advcheckbox posts an explicit 0 when unticked.
        $form = (array) instance_config::load($enrolid);
        $form['allowtemporary'] = 0;
        $form['flexaccessform'] = 1;
        $form['temporaryaccesskey'] = '';
        $form['quickreggatepassword'] = '';
        $instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        $plugin->update_instance($instance, (object) $form);
        $row = instance_config::load($enrolid);
        $this->assertSame(0, (int) $row->allowtemporary);
        $this->assertSame(9, (int) $row->maxparticipants);
    }

    /**
     * Empty secrets keep their hashes; a new secret replaces only its own hash.
     */
    public function test_secrets_keep_or_replace_their_hash(): void {
        $enrolid = $this->full_instance();
        $before = $this->row($enrolid);
        instance_config::save($enrolid, ['temporaryaccesskey' => '', 'quickreggatepassword' => '  ']);
        $this->assertEquals($before, $this->row($enrolid));

        instance_config::save($enrolid, ['temporaryaccesskey' => 'KEY-2']);
        $row = $this->row($enrolid);
        $this->assertTrue(password_verify('KEY-2', $row['temporaryaccesskeyhash']));
        $this->assertSame($before['quickreggatepasswordhash'], $row['quickreggatepasswordhash']);
    }

    /**
     * Invalid values are rejected without any write, including values valid alone but not together.
     *
     * @dataProvider invalid_patch_provider
     * @param array $patch Invalid patch.
     */
    public function test_invalid_patch_writes_nothing(array $patch): void {
        global $DB;
        $enrolid = $this->full_instance();
        $before = (array) instance_config::load($enrolid);
        $core = $DB->get_record('enrol', ['id' => $enrolid]);
        try {
            enrol_get_plugin('flexaccess')->update_instance(clone $core, (object) ($patch + ['name' => 'Changed']));
            $this->fail('Invalid patch accepted');
        } catch (\invalid_parameter_exception $e) {
            $this->assertEquals($before, (array) instance_config::load($enrolid));
            $this->assertEquals($core, $DB->get_record('enrol', ['id' => $enrolid]), 'Core row untouched.');
        }
    }

    /**
     * Invalid patches.
     *
     * @return array
     */
    public static function invalid_patch_provider(): array {
        return [
            'negative capacity' => [['maxparticipants' => -1, 'allowquick' => 0]],
            'fraction' => [['maxparticipants' => '2.5']],
            'text capacity' => [['maxparticipants' => 'many']],
            'negative bound' => [['availablefrom' => -5]],
            'unknown expiry action' => [['expiryaction' => 'delete']],
            'unknown list access' => [['participantlistaccess' => 'everyone', 'allowguest' => 0]],
            'start after stored end' => [['availablefrom' => 1896134400]],
            'end before stored start' => [['availableuntil' => 1893455999, 'maxparticipants' => 4]],
        ];
    }

    /**
     * Invalid configuration on creation creates neither the core row nor the extension row.
     */
    public function test_invalid_add_instance_creates_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $count = $DB->count_records('enrol', ['courseid' => $course->id]);
        try {
            enrol_get_plugin('flexaccess')->add_instance($course, ['availablefrom' => 2000, 'availableuntil' => 1000]);
            $this->fail('Invalid window accepted');
        } catch (\invalid_parameter_exception $e) {
            $this->assertSame($count, $DB->count_records('enrol', ['courseid' => $course->id]));
            $this->assertSame(0, $DB->count_records('enrol_flexaccess_instance'));
        }
    }

    /**
     * Repeating an identical patch writes nothing (timemodified unchanged).
     */
    public function test_identical_patch_is_idempotent(): void {
        $enrolid = $this->full_instance();
        $this->mock_clock_with_frozen(1900000000);
        $this->assertTrue(instance_config::save($enrolid, ['maxparticipants' => 3]));
        $first = (array) instance_config::load($enrolid);
        $this->assertSame(1900000000, (int) $first['timemodified']);
        $this->mock_clock_with_frozen(1900000500);
        $this->assertFalse(instance_config::save($enrolid, ['maxparticipants' => 3]));
        $this->assertFalse(instance_config::save($enrolid, ['maxparticipants' => '3', 'allowquick' => true]));
        $this->assertEquals($first, (array) instance_config::load($enrolid));
    }

    /**
     * The enrolment period is mirrored into the core enrol row (what the edit form shows).
     */
    public function test_enrolment_period_mirrored_into_core_row(): void {
        global $DB;
        $enrolid = $this->full_instance();
        $this->assertSame(5 * DAYSECS, (int) $DB->get_field('enrol', 'enrolperiod', ['id' => $enrolid]));
        instance_config::save($enrolid, ['enrolperiod' => 0]);
        $this->assertSame(0, (int) $DB->get_field('enrol', 'enrolperiod', ['id' => $enrolid]));
        // A drifted core value is corrected by the next write.
        $DB->set_field('enrol', 'enrolperiod', 77, ['id' => $enrolid]);
        instance_config::save($enrolid, ['enrolperiod' => DAYSECS]);
        $this->assertSame(DAYSECS, (int) $DB->get_field('enrol', 'enrolperiod', ['id' => $enrolid]));
    }

    /**
     * The upgrade step brings existing core rows in line with the FlexAccess enrolment period.
     */
    public function test_upgrade_aligns_core_enrolment_period(): void {
        global $CFG, $DB;
        $enrolid = $this->full_instance();
        $DB->set_field('enrol', 'enrolperiod', null, ['id' => $enrolid]);
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/enrol/flexaccess/db/upgrade.php');
        set_config('version', 2026101800, 'enrol_flexaccess');
        $this->assertTrue(xmldb_enrol_flexaccess_upgrade(2026101800));
        $this->assertSame(5 * DAYSECS, (int) $DB->get_field('enrol', 'enrolperiod', ['id' => $enrolid]));
    }

    /**
     * The effective policy and the participant-list override reflect a save immediately.
     */
    public function test_policy_and_override_reflect_save_immediately(): void {
        global $DB;
        $enrolid = $this->full_instance();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $courseid = (int) $DB->get_field('enrol', 'courseid', ['id' => $enrolid]);
        $contextid = \context_course::instance($courseid)->id;
        $override = ['contextid' => $contextid, 'roleid' => local\participant_role::get_id(),
            'capability' => 'moodle/course:viewparticipants'];
        // Warm the cache with the stored state.
        $this->assertSame('hide', \enrol_flexaccess\api::get_effective_policy($courseid)->participantlistaccess);
        $this->assertTrue($DB->record_exists('role_capabilities', $override));

        instance_config::save($enrolid, ['participantlistaccess' => 'show']);
        $this->assertSame('show', \enrol_flexaccess\api::get_effective_policy($courseid)->participantlistaccess);
        $this->assertFalse($DB->record_exists('role_capabilities', $override), 'Override follows the saved state.');
    }
}
