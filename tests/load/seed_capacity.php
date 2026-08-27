<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Seed a course that is full except for a defined number of free seats.
 *
 * The capacity race plan needs a target where the outcome is unambiguous: with one seat left,
 * exactly one of many simultaneous requests may be granted access. Filling the course here rather
 * than in the load plan keeps the plan free of setup logic and makes the expectation exact.
 *
 * Prints "export KEY='value'" lines for BASE_URL, COURSEID, INSTANCEID and FREE_SEATS.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

$freeseats = max(1, (int) getenv('FREE_SEATS') ?: 1);
$capacity = max($freeseats + 1, (int) getenv('CAPACITY') ?: 20);

$now = time();
$course = create_course((object) [
    'fullname' => 'FlexAccess capacity ' . $now,
    'shortname' => 'flexcap' . $now,
    'category' => 1,
    'visible' => 1,
]);

$plugin = enrol_get_plugin('flexaccess');
$instanceid = $plugin->add_instance($course, [
    'status' => ENROL_INSTANCE_ENABLED,
    'customint1' => 0,
]);
\enrol_flexaccess\local\instance_config::save($instanceid, [
    'allowtemporary' => 1,
    'temporarylifetime' => DAYSECS,
    'maxparticipants' => $capacity,
]);

// Occupy every seat but the ones the race is meant to compete for.
$taken = $capacity - $freeseats;
for ($i = 0; $i < $taken; $i++) {
    $result = \enrol_flexaccess\local\enrol_service::reserve_and_enrol(
        $instanceid,
        static function () use ($i, $now) {
            return \auth_flexaccess\api::create_temporary_user($now + DAYSECS, 0, null, $now);
        },
        $now
    );
    if ($result->status !== 'enrolled') {
        cli_error("Konnte Platz $i nicht belegen (Status: {$result->status}).");
    }
}

\cache::make('enrol_flexaccess', 'policy')->purge();

echo "export BASE_URL='" . $CFG->wwwroot . "'\n";
echo "export COURSEID='" . $course->id . "'\n";
echo "export INSTANCEID='" . $instanceid . "'\n";
echo "export FREE_SEATS='" . $freeseats . "'\n";
