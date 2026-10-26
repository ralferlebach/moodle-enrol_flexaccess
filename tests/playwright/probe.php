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
 * Server-side probe for the browser journeys (test sites only): the stored state a journey produced.
 *
 * Usage: php probe.php instance <enrolid> | timezone <zone> | openwindow <enrolid>
 * Prints one JSON object: the extension row, the core enrolment period, the bounds as the site
 * administrator sees them (Y-m-d H:i in their time zone) and the effective course policy; or sets the
 * administrator's time zone (effective with the next login).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');

[$command, $args] = [$argv[1] ?? '', array_slice($argv, 2)];
\core\session\manager::set_user(get_admin());

if ($command === 'timezone' && !empty($args[0])) {
    // The administrator's own time zone; takes effect with the next login.
    $DB->set_field('user', 'timezone', $args[0], ['id' => get_admin()->id]);
    set_config('forcetimezone', 99);
    echo json_encode(['timezone' => $args[0]]), "\n";
    exit(0);
}
if ($command === 'openwindow' && !empty($args[0])) {
    // A journey's precondition, independent of what earlier journeys left: open window, no capacity limit.
    \enrol_flexaccess\local\instance_config::save((int) $args[0], [
        'availablefrom' => 0,
        'availableuntil' => 0,
        'maxparticipants' => 0,
    ]);
    echo json_encode(['openwindow' => (int) $args[0]]), "\n";
    exit(0);
}
if ($command !== 'instance' || empty($args[0])) {
    cli_error('Usage: php probe.php instance <enrolid> | timezone <zone> | openwindow <enrolid>');
}
$enrolid = (int) $args[0];
$row = \enrol_flexaccess\local\instance_config::load($enrolid);
$enrol = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
$local = static function (int $time): string {
    return $time > 0 ? userdate($time, '%Y-%m-%d %H:%M', 99, false) : '';
};
$policy = \enrol_flexaccess\api::get_effective_policy((int) $enrol->courseid);
$now = \core\di::get(\core\clock::class)->time();
echo json_encode([
    'row' => $row,
    'coreenrolperiod' => (int) $enrol->enrolperiod,
    'local' => ['availablefrom' => $local((int) $row->availablefrom), 'availableuntil' => $local((int) $row->availableuntil)],
    'policy' => [
        'allowtemporary' => (bool) $policy->allowtemporary,
        'availablefrom' => (int) $policy->availablefrom,
        'availableuntil' => (int) $policy->availableuntil,
        'maxparticipants' => (int) $policy->maxparticipants,
        'windowopen' => \enrol_flexaccess\local\access_window::is_open(
            (int) $policy->availablefrom,
            (int) $policy->availableuntil,
            $now
        ),
    ],
]), "\n";
