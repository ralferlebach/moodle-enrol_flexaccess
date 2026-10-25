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
 * Round trip of the instance edit form: open, submit as the browser would, reload (issue #12).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use enrol_flexaccess\local\instance_config;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/enrol/editinstance_form.php');

/**
 * Edit-form round trip through Moodle's real enrol_instance_edit_form.
 *
 * Every test renders the form, collects exactly what a browser would post back (selected options,
 * checked boxes, text values), submits it through the real form class and the plugin's
 * update_instance(), and compares the stored extension row.
 *
 * @package    enrol_flexaccess
 * @covers \enrol_flexaccess_plugin
 * @covers \enrol_flexaccess\local\instance_config
 */
final class instance_form_roundtrip_test extends \advanced_testcase {
    /** Fields of the extension row the form manages. */
    private const FORM_FIELDS = ['availablefrom', 'availableuntil', 'maxparticipants', 'participantlistaccess',
        'allowtemporary', 'allowquick', 'allowguest', 'allownormallogin', 'allowmagiclogin', 'temporarylifetime',
        'enrolperiod', 'expiryaction', 'temporaryaccesskeymode', 'quickreggatemode', 'quickreggatedomains'];

    /**
     * Create a course with a FlexAccess method and the given stored configuration.
     *
     * @param array $config Extension fields to store.
     * @return int Enrol instance id.
     */
    private function make_instance(array $config): int {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('flexaccess');
        return $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED] + $config);
    }

    /**
     * Build the real edit form for an instance.
     *
     * @param int $enrolid Enrol instance id.
     * @return \enrol_instance_edit_form
     */
    private function build_form(int $enrolid): \enrol_instance_edit_form {
        global $DB, $PAGE;
        $instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        $context = \context_course::instance((int) $instance->courseid);
        $PAGE->set_url('/enrol/editinstance.php', ['courseid' => $instance->courseid, 'id' => $enrolid, 'type' => 'flexaccess']);
        $PAGE->set_context($context);
        return new \enrol_instance_edit_form(null, [
            $instance, enrol_get_plugin('flexaccess'), $context, 'flexaccess', new \moodle_url('/enrol/instances.php'),
        ]);
    }

    /**
     * Exactly what a browser posts for the rendered form, as a $_POST-shaped array.
     *
     * @param \enrol_instance_edit_form $form Built form.
     * @return array
     */
    private function browser_post(\enrol_instance_edit_form $form): array {
        $html = $form->render();
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        $pairs = [];
        $xpath = new \DOMXPath($doc);
        foreach ($xpath->query('//input | //select | //textarea') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $name = $node->getAttribute('name');
            if ($name === '' || $node->hasAttribute('disabled')) {
                continue;
            }
            if ($node->tagName === 'select') {
                $selected = $xpath->query('.//option[@selected]', $node);
                $option = $selected->length ? $selected->item(0) : $xpath->query('.//option', $node)->item(0);
                $pairs[] = [$name, $option ? $option->getAttribute('value') : ''];
            } else if ($node->tagName === 'textarea') {
                $pairs[] = [$name, $node->textContent];
            } else {
                $type = strtolower($node->getAttribute('type') ?: 'text');
                if (in_array($type, ['checkbox', 'radio'], true) && !$node->hasAttribute('checked')) {
                    continue;
                }
                if (in_array($type, ['submit', 'button', 'image', 'reset'], true) && $name !== 'submitbutton') {
                    continue;
                }
                $pairs[] = [$name, $node->getAttribute('value')];
            }
        }
        $query = implode('&', array_map(fn($p) => rawurlencode($p[0]) . '=' . rawurlencode($p[1]), $pairs));
        parse_str($query, $post);
        return $post;
    }

    /**
     * Open the edit form, optionally change some posted values, submit and save as editinstance.php does.
     *
     * @param int $enrolid Enrol instance id.
     * @param callable|null $change Receives the post array by reference before submission.
     * @return array|null Validation errors, or null when the form was saved.
     */
    private function open_and_submit(int $enrolid, ?callable $change = null): ?array {
        global $DB;
        $post = $this->browser_post($this->build_form($enrolid));
        if ($change) {
            $change($post);
        }
        unset($post['sesskey'], $post['_qf__enrol_instance_edit_form']);
        \enrol_instance_edit_form::mock_submit($post);
        $form = $this->build_form($enrolid);
        $data = $form->get_data();
        if (!$data) {
            $mform = (fn() => $this->_form)->call($form);
            $errors = [];
            foreach (array_keys($post) as $name) {
                if ($mform->getElementError($name)) {
                    $errors[$name] = $mform->getElementError($name);
                }
            }
            return $errors;
        }
        $instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        enrol_get_plugin('flexaccess')->update_instance($instance, $data);
        $_POST = [];
        return null;
    }

    /**
     * The stored form-managed fields.
     *
     * @param int $enrolid Enrol instance id.
     * @return array
     */
    private function stored(int $enrolid): array {
        $row = (array) instance_config::load($enrolid);
        $out = [];
        foreach (self::FORM_FIELDS as $field) {
            $out[$field] = $row[$field];
        }
        return $out;
    }

    /**
     * Window and capacity combinations.
     *
     * @return array
     */
    public static function window_provider(): array {
        $from = 1893456000; // 2030-01-01 00:00 UTC.
        $until = 1896134400; // 2030-02-01 00:00 UTC.
        return [
            'no limits, unlimited' => [0, 0, 0],
            'only from, 17' => [$from, 0, 17],
            'only until, unlimited' => [0, $until, 0],
            'both, 17' => [$from, $until, 17],
        ];
    }

    /**
     * Opening and saving the form without changes keeps window and capacity (the reported bug).
     *
     * @dataProvider window_provider
     * @param int $from Stored availablefrom.
     * @param int $until Stored availableuntil.
     * @param int $max Stored maxparticipants.
     */
    public function test_unchanged_resave_keeps_window_and_capacity(int $from, int $until, int $max): void {
        $enrolid = $this->make_instance([
            'availablefrom' => $from, 'availableuntil' => $until, 'maxparticipants' => $max,
            'allowtemporary' => 1, 'enrolperiod' => 3 * DAYSECS, 'temporarylifetime' => 2 * HOURSECS,
        ]);
        $before = instance_config::load($enrolid);

        $this->assertNull($this->open_and_submit($enrolid));
        $this->assertNull($this->open_and_submit($enrolid));

        $after = instance_config::load($enrolid);
        $this->assertSame($from, (int) $after->availablefrom);
        $this->assertSame($until, (int) $after->availableuntil);
        $this->assertSame($max, (int) $after->maxparticipants);
        $this->assertEquals((array) $before, (array) $after, 'An unchanged save must not touch the row.');
    }

    /**
     * The form shows the stored window and capacity.
     */
    public function test_form_shows_stored_window_and_capacity(): void {
        $enrolid = $this->make_instance(['availablefrom' => 1893456000, 'availableuntil' => 1896134400,
            'maxparticipants' => 17]);
        $this->setTimezone('UTC');
        $post = $this->browser_post($this->build_form($enrolid));
        $this->assertSame('1', $post['availablefrom']['enabled'] ?? null);
        $this->assertSame(['2030', '1', '1', '0', '0'], [$post['availablefrom']['year'], $post['availablefrom']['month'],
            $post['availablefrom']['day'], $post['availablefrom']['hour'], $post['availablefrom']['minute']]);
        $this->assertSame('1', $post['availableuntil']['enabled'] ?? null);
        $this->assertSame('2', $post['availableuntil']['month']);
        $this->assertSame('17', $post['maxparticipants']);
    }

    /**
     * Changing one field in the form leaves every other field as stored.
     */
    public function test_changing_capacity_keeps_window(): void {
        $enrolid = $this->make_instance(['availablefrom' => 1893456000, 'availableuntil' => 1896134400,
            'maxparticipants' => 17, 'allowquick' => 1]);
        $before = $this->stored($enrolid);
        $this->assertNull($this->open_and_submit($enrolid, function (array &$post): void {
            $post['maxparticipants'] = '0';
        }));
        $expected = ['maxparticipants' => 0] + $before;
        $this->assertEquals($expected, $this->stored($enrolid));
    }

    /**
     * Unticking one optional date clears only that bound.
     */
    public function test_disabling_end_keeps_start(): void {
        $enrolid = $this->make_instance(['availablefrom' => 1893456000, 'availableuntil' => 1896134400,
            'maxparticipants' => 17]);
        $this->assertNull($this->open_and_submit($enrolid, function (array &$post): void {
            unset($post['availableuntil']['enabled']);
        }));
        $row = instance_config::load($enrolid);
        $this->assertSame(1893456000, (int) $row->availablefrom);
        $this->assertSame(0, (int) $row->availableuntil);
        $this->assertSame(17, (int) $row->maxparticipants);
    }

    /**
     * Enabling a bound that was off stores the chosen time.
     */
    public function test_enabling_start_stores_time(): void {
        $enrolid = $this->make_instance(['maxparticipants' => 5]);
        $this->setTimezone('UTC');
        $this->assertNull($this->open_and_submit($enrolid, function (array &$post): void {
            $post['availablefrom'] = ['day' => '15', 'month' => '6', 'year' => '2031', 'hour' => '8', 'minute' => '30',
                'enabled' => '1'];
        }));
        $row = instance_config::load($enrolid);
        $this->assertSame(1939278600, (int) $row->availablefrom); // 2031-06-15 08:30 UTC.
        $this->assertSame(0, (int) $row->availableuntil);
        $this->assertSame(5, (int) $row->maxparticipants);
    }

    /**
     * An end before (or equal to) the start is rejected and nothing is written.
     */
    public function test_invalid_interval_rejected(): void {
        $enrolid = $this->make_instance(['availablefrom' => 1893456000, 'availableuntil' => 1896134400,
            'maxparticipants' => 17]);
        $this->setTimezone('UTC');
        $before = instance_config::load($enrolid);
        $errors = $this->open_and_submit($enrolid, function (array &$post): void {
            $post['availableuntil'] = ['day' => '1', 'month' => '1', 'year' => '2030', 'hour' => '0', 'minute' => '0',
                'enabled' => '1'];
        });
        $this->assertArrayHasKey('availableuntil', $errors);
        $errors = $this->open_and_submit($enrolid, function (array &$post): void {
            $post['maxparticipants'] = '-3';
        });
        $this->assertArrayHasKey('maxparticipants', $errors);
        $this->assertEquals((array) $before, (array) instance_config::load($enrolid));
    }

    /**
     * Time zones with odd offsets, DST changes and timestamps with seconds.
     *
     * @return array
     */
    public static function timezone_provider(): array {
        return [
            // Berlin, start of summer time 2030-03-31 01:00 UTC; minute before and after.
            'Berlin, around spring change' => ['Europe/Berlin', 1901149140, 1901149260],
            // Berlin, end of summer time 2030-10-27: 00:30 UTC and 01:30 UTC are both 02:30 local.
            'Berlin, ambiguous autumn hour (first)' => ['Europe/Berlin', 1919291400, 0],
            'Berlin, ambiguous autumn hour (second)' => ['Europe/Berlin', 1919295000, 0],
            'St. John\'s, -03:30' => ['America/St_Johns', 1893456000, 1896134400],
            'Kathmandu, +05:45' => ['Asia/Kathmandu', 1893456000, 0],
            // Lord Howe, end of 30-minute DST: both bounds show as 01:45 local on 2030-04-07.
            'Lord Howe, ambiguous half hour' => ['Australia/Lord_Howe', 1901717100, 1901718900],
            'UTC with seconds' => ['UTC', 1893456037, 1896134459],
            // Regression of the reported bug: 21.-22. October 2026, 00:00-23:59 local (summer time).
            'Berlin, 21-22 October 2026 (CEST)' => ['Europe/Berlin', 1792533600, 1792706340],
            'Los Angeles, 21-22 October 2026 (PDT)' => ['America/Los_Angeles', 1792566000, 1792738740],
            // Windows across the end of summer time (Berlin 2026-10-25, Los Angeles 2026-11-01).
            'Berlin, across the autumn change' => ['Europe/Berlin', 1792836000, 1793012400],
            'Los Angeles, across the autumn change' => ['America/Los_Angeles', 1793473200, 1793649600],
        ];
    }

    /**
     * A window entered as 21.-22. October (00:00 to 23:59 local) in each time zone.
     *
     * @return array
     */
    public static function october_provider(): array {
        return [
            'Europe/Berlin (CEST, UTC+2)' => ['Europe/Berlin', 1792533600, 1792706340],
            'America/Los_Angeles (PDT, UTC-7)' => ['America/Los_Angeles', 1792566000, 1792738740],
        ];
    }

    /**
     * Regression: the window entered as 21.-22. October is stored as the user's local days, shown again as
     * entered, kept by an unchanged save, and really blocks the entry outside it.
     *
     * @dataProvider october_provider
     * @param string $timezone User time zone.
     * @param int $from Expected availablefrom (UTC).
     * @param int $until Expected availableuntil (UTC).
     */
    public function test_october_window_round_trip_and_access(string $timezone, int $from, int $until): void {
        global $DB;
        $enrolid = $this->make_instance([]);
        instance_config::save($enrolid, ['allowtemporary' => 1, 'temporarylifetime' => DAYSECS, 'maxparticipants' => 17]);
        set_config('auth', 'flexaccess');
        set_config('allowwidening', 1, 'enrol_flexaccess');
        \core\plugin_manager::reset_caches();
        $this->setTimezone($timezone);
        $this->assertNull($this->open_and_submit($enrolid, function (array &$post): void {
            $post['availablefrom'] = ['day' => '21', 'month' => '10', 'year' => '2026', 'hour' => '0', 'minute' => '0',
                'enabled' => '1'];
            $post['availableuntil'] = ['day' => '22', 'month' => '10', 'year' => '2026', 'hour' => '23', 'minute' => '59',
                'enabled' => '1'];
        }));
        $row = instance_config::load($enrolid);
        $stored = [(int) $row->availablefrom, (int) $row->availableuntil, (int) $row->maxparticipants];
        $this->assertSame([$from, $until, 17], $stored);

        // Reopened, the form shows exactly the entered days; an unchanged save writes nothing.
        $post = $this->browser_post($this->build_form($enrolid));
        $this->assertSame(['21', '10', '2026', '0', '0'], [$post['availablefrom']['day'], $post['availablefrom']['month'],
            $post['availablefrom']['year'], $post['availablefrom']['hour'], $post['availablefrom']['minute']]);
        $this->assertSame(['22', '23', '59'], [$post['availableuntil']['day'], $post['availableuntil']['hour'],
            $post['availableuntil']['minute']]);
        $this->assertNull($this->open_and_submit($enrolid));
        $this->assertEquals($row, instance_config::load($enrolid));

        // The stored window decides the real entry: closed before, open inside, closed after.
        $courseid = (int) $DB->get_field('enrol', 'courseid', ['id' => $enrolid]);
        $clock = $this->mock_clock_with_frozen($from - 1);
        $this->assertFalse(api::offers_anonymous_entry($courseid), 'One second before the start.');
        $clock->set_to($from);
        $this->assertTrue(api::offers_anonymous_entry($courseid), 'At the start (local midnight).');
        $clock->set_to($until - 1);
        $this->assertTrue(api::offers_anonymous_entry($courseid), 'Last second inside the window.');
        $clock->set_to($until);
        $this->assertFalse(api::offers_anonymous_entry($courseid), 'At the end (exclusive).');
    }

    /**
     * Invalid bounds (end before or at the start) are rejected and change nothing.
     */
    public function test_inverted_october_bounds_are_rejected(): void {
        $enrolid = $this->make_instance(['availablefrom' => 1792533600, 'availableuntil' => 1792706340]);
        $this->setTimezone('Europe/Berlin');
        $before = instance_config::load($enrolid);
        $errors = $this->open_and_submit($enrolid, function (array &$post): void {
            $post['availableuntil'] = ['day' => '20', 'month' => '10', 'year' => '2026', 'hour' => '23', 'minute' => '59',
                'enabled' => '1'];
        });
        $this->assertArrayHasKey('availableuntil', (array) $errors);
        $this->assertEquals($before, instance_config::load($enrolid));
    }

    /**
     * An unchanged save keeps the exact stored seconds in every time zone, including DST edges.
     *
     * @dataProvider timezone_provider
     * @param string $timezone User time zone.
     * @param int $from Stored availablefrom.
     * @param int $until Stored availableuntil.
     */
    public function test_unchanged_resave_is_exact_in_time_zones(string $timezone, int $from, int $until): void {
        $this->mock_clock_with_frozen(1893000000);
        $enrolid = $this->make_instance(['availablefrom' => $from, 'availableuntil' => $until, 'maxparticipants' => 3]);
        $this->setTimezone($timezone);
        $before = instance_config::load($enrolid);
        $this->assertNull($this->open_and_submit($enrolid));
        $this->assertEquals((array) $before, (array) instance_config::load($enrolid));
    }

    /**
     * Times chosen in the form are stored as the instant the user meant, regardless of the time zone.
     */
    public function test_entered_time_is_the_users_local_time(): void {
        $enrolid = $this->make_instance([]);
        $this->setTimezone('America/St_Johns');
        $this->assertNull($this->open_and_submit($enrolid, function (array &$post): void {
            $post['availablefrom'] = ['day' => '1', 'month' => '1', 'year' => '2030', 'hour' => '0', 'minute' => '0',
                'enabled' => '1'];
        }));
        // Midnight in St. John's (UTC-03:30) is 03:30 UTC.
        $this->assertSame(1893456000 + 3 * HOURSECS + 30 * MINSECS, (int) instance_config::load($enrolid)->availablefrom);
    }
}
