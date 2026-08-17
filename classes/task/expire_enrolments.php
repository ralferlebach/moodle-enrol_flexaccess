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
 * Scheduled task applying FlexAccess enrolment expiry.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\task;

/** Expire course enrolments independently from account lifetime. */
final class expire_enrolments extends \core\task\scheduled_task {
    /** @return string */
    public function get_name(): string { return get_string('task:expireenrolments', 'enrol_flexaccess'); }
    /** Execute task. */
    public function execute(): void {
        // Phase 2: suspend or unenrol due user_enrolments according to instance policy.
    }
}
