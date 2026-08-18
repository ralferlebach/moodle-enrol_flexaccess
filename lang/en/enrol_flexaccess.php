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
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accesskeyinvalid'] = 'The access key is not valid.';
$string['accesskeymode:course'] = 'Use a course-specific access key';
$string['accesskeymode:inherit'] = 'Inherit system setting';
$string['accessnotopen'] = 'FlexAccess access is not available at this time.';
$string['allowguest'] = 'Allow normal guest access';
$string['allowguest_help'] = 'Offer Moodle guest access as one of the FlexAccess entry options.';
$string['allownormallogin'] = 'Allow normal login';
$string['allownormallogin_help'] = 'Keep the standard Moodle login available alongside FlexAccess methods.';
$string['allowquick'] = 'Allow quick registration';
$string['allowquick_help'] = 'Offer a lightweight registration that creates a persistent account with minimal detail.';
$string['allowtemporary'] = 'Allow temporary users';
$string['allowtemporary_help'] = 'Offer low-barrier temporary access that creates a short-lived account and enrols it.';
$string['allowwidening'] = 'Allow lower scopes to widen policy';
$string['allowwidening_desc'] = 'If disabled, a course/category cannot re-enable an access method prohibited by a higher scope.';
$string['availablefrom'] = 'Available from';
$string['availablefrom_help'] = 'Earliest time FlexAccess access is offered by this instance. Empty/disabled means no lower bound. This is separate from account lifetime and enrolment lifetime, and is combinable with the access key.';
$string['availableuntil'] = 'Available until';
$string['availableuntil_help'] = 'Latest time FlexAccess access is offered by this instance (exclusive). Empty/disabled means no upper bound.';
$string['coursefull'] = 'The maximum number of participants for this access method has been reached.';
$string['error:capacitylock'] = 'Could not acquire the capacity lock. Please try again.';
$string['error:maxparticipants'] = 'The maximum number of participants must be 0 (unlimited) or a positive number.';
$string['error:windowrange'] = 'The "available until" time must be after the "available from" time.';
$string['expiryaction'] = 'Expiry action';
$string['expiryaction:suspend'] = 'Suspend the enrolment';
$string['expiryaction:unenrol'] = 'Unenrol the user';
$string['expiryaction_help'] = 'What happens to a FlexAccess enrolment when it expires.';
$string['flexaccess:config'] = 'Configure FlexAccess enrolment instances';
$string['flexaccess:manage'] = 'Manage FlexAccess enrolments';
$string['flexaccess:unenrol'] = 'Unenrol users from a FlexAccess instance';
$string['hide'] = 'Hide';
$string['maxparticipants'] = 'Maximum participants';
$string['maxparticipants_help'] = 'Maximum number of active FlexAccess enrolments for this instance. 0 means unlimited. Expired access frees capacity. There is no waitlist.';
$string['participantvisibilitydefault'] = 'Default temporary-user participant-list visibility';
$string['participantvisibilitydefault_desc'] = 'System default. Each FlexAccess course enrolment instance can inherit, show or hide.';
$string['pluginname'] = 'FlexAccess enrolment';
$string['privacy:metadata'] = 'FlexAccess enrolment stores policy and enrolment configuration only; user enrolments are held by Moodle core.';
$string['settings:access'] = 'Access window and capacity';
$string['settings:accesskey'] = 'Temporary-user access key';
$string['settings:accesskeygate'] = 'Access key';
$string['settings:defaults'] = 'Policy defaults';
$string['settings:lifecycle'] = 'Lifetimes and expiry';
$string['settings:methods'] = 'Access methods';
$string['show'] = 'Show';
$string['status'] = 'Enable FlexAccess enrolment';
$string['task:expireenrolments'] = 'Expire FlexAccess course enrolments';
$string['temporaryaccesskey'] = 'System-wide access key';
$string['temporaryaccesskey_desc'] = 'Enter a new shared access key. Only a secure hash is stored. Leave blank to retain the current key; use the separate setting to enable or disable the requirement.';
$string['temporaryaccesskey_help'] = 'The shared key a visitor must enter for temporary access. Leave blank to keep the current key.';
$string['temporaryaccesskeymode'] = 'Access key mode';
$string['temporaryaccesskeymode:course'] = 'Use a course access key';
$string['temporaryaccesskeymode:inherit'] = 'Inherit site setting';
$string['temporaryaccesskeymode_help'] = 'Whether temporary entry uses no course key (inherit the site setting) or a key specific to this course.';
$string['temporaryaccesskeyrequired'] = 'Require a system-wide access key';
$string['temporaryaccesskeyrequired_desc'] = 'Requires an access key system-wide for FlexAccess modes that create a temporary user. Courses may inherit it or replace it with their own key.';
$string['temporarylifetime'] = 'Temporary account lifetime';
$string['temporarylifetime_help'] = 'How long a temporary account remains active before it expires.';
