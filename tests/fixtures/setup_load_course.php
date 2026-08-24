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
 * CLI fixture: create a visible course with an enabled FlexAccess enrolment instance that offers
 * anonymous temporary access, and print its course id.
 *
 * Used by the load (JMeter) and browser (Playwright) suites so they exercise a real FlexAccess
 * entry point rather than only the Moodle login page. Intended for disposable CI test sites only.
 *
 * Usage: php enrol/flexaccess/tests/fixtures/setup_load_course.php
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/clilib.php');

$category = \core_course_category::get_default();

$course = create_course((object) [
    'fullname' => 'FlexAccess Load Test',
    'shortname' => 'FALT' . time(),
    'category' => $category->id,
    'visible' => 1,
]);

set_config('allowwidening', 1, 'enrol_flexaccess');
$plugin = enrol_get_plugin('flexaccess');
$enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
\enrol_flexaccess\local\instance_config::save($enrolid, [
    'allowtemporary' => 1,
    'maxparticipants' => 0,
]);

// Print only the course id on stdout so a CI job can capture it.
echo $course->id . "\n";
