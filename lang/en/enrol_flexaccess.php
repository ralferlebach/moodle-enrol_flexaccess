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
 * Language strings for enrol_flexaccess.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'FlexAccess enrolment';
$string['settings:defaults'] = 'Policy defaults';
$string['participantvisibilitydefault'] = 'Default temporary-user participant-list visibility';
$string['participantvisibilitydefault_desc'] = 'System default. Each FlexAccess course enrolment instance can inherit, show or hide.';
$string['show'] = 'Show';
$string['hide'] = 'Hide';
$string['allowwidening'] = 'Allow lower scopes to widen policy';
$string['allowwidening_desc'] = 'If disabled, a course/category cannot re-enable an access method prohibited by a higher scope.';
$string['task:expireenrolments'] = 'Expire FlexAccess course enrolments';
$string['privacy:metadata'] = 'FlexAccess enrolment stores policy and enrolment configuration only; user enrolments are held by Moodle core.';

$string['settings:accesskey'] = 'Temporary-user access key';
$string['temporaryaccesskeyrequired'] = 'Require a system-wide access key';
$string['temporaryaccesskeyrequired_desc'] = 'Requires an access key system-wide for FlexAccess modes that create a temporary user. Courses may inherit it or replace it with their own key.';
$string['temporaryaccesskey'] = 'System-wide access key';
$string['temporaryaccesskey_desc'] = 'Enter a new shared access key. Only a secure hash is stored. Leave blank to retain the current key; use the separate setting to enable or disable the requirement.';
$string['accesskeymode:inherit'] = 'Inherit system setting';
$string['accesskeymode:course'] = 'Use a course-specific access key';
$string['accesskeyinvalid'] = 'The access key is not valid.';
