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
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_help'] = 'How long a new enrolment stays active, independent of the temporary account lifetime. When set, the enrolment ends after this period and the expiry action is applied. Leave at zero to keep the enrolment until the account expires or it is removed manually.';
$string['error:capacitylock'] = 'Could not acquire the capacity lock. Please try again.';
$string['error:maxparticipants'] = 'The maximum number of participants must be 0 (unlimited) or a positive number.';
$string['error:windowrange'] = 'The "available until" time must be after the "available from" time.';
$string['expiryaction'] = 'Expiry action';
$string['expiryaction:suspend'] = 'Suspend the enrolment';
$string['expiryaction:unenrol'] = 'Unenrol the user';
$string['expiryaction_help'] = 'What happens to a FlexAccess enrolment when it expires.';
$string['flexaccess:config'] = 'Configure FlexAccess enrolment instances';
$string['flexaccess:unenrol'] = 'Unenrol users from a FlexAccess instance';
$string['gate:domain'] = 'Allowed email domains';
$string['gate:inherit'] = 'Inherit site default';
$string['gate:none'] = 'No additional gate';
$string['gate:password'] = 'Shared password';
$string['hide'] = 'Hide';
$string['instance:quickreggatedomains'] = 'Allowed email domains';
$string['instance:quickreggatemode'] = 'Quick-registration gate';
$string['instance:quickreggatepassword'] = 'Gate password';
$string['maxparticipants'] = 'Maximum participants';
$string['maxparticipants_help'] = 'Maximum number of active FlexAccess enrolments for this instance. 0 means unlimited. Expired access frees capacity. There is no waitlist.';
$string['participantrole'] = 'FlexAccess participant';
$string['participantrole_desc'] = 'Dedicated role assigned to temporary and quick-registered FlexAccess visitors. It mirrors the student role but lets a course hide its participant list from these visitors.';
$string['participantvisibility'] = 'Participant-list access for temporary visitors';
$string['participantvisibility:inherit'] = 'Inherit site default';
$string['participantvisibility_help'] = 'Controls whether temporary and quick-registered visitors of this course may open the course participant list. "Deny" prevents them from viewing the participants page; "Inherit" uses the site default. Note: this does NOT hide temporary visitors from the participant list shown to other users — Moodle offers no stable extension point for that, so that feature is not provided.';
$string['participantvisibilitydefault'] = 'Default participant-list access for temporary visitors';
$string['participantvisibilitydefault_desc'] = 'System default for whether temporary visitors may view the participant list. Each FlexAccess enrolment instance can inherit, allow or deny.';
$string['pluginname'] = 'FlexAccess enrolment';
$string['privacy:metadata'] = 'FlexAccess enrolment stores policy and enrolment configuration only; user enrolments are held by Moodle core.';
$string['restrictionrole'] = 'FlexAccess restricted visitor';
$string['restrictionrole_desc'] = 'System-level role assigned to temporary FlexAccess visitors that only withdraws messaging and profile-editing capabilities. It grants nothing and is removed on conversion to a full account.';
$string['setting:quickreggatedomains'] = 'Allowed email domains';
$string['setting:quickreggatedomains_desc'] = 'One domain per line (or comma separated), e.g. university.edu. Subdomains are accepted.';
$string['setting:quickreggatemode'] = 'Gate type';
$string['setting:quickreggatemode_desc'] = 'Restrict quick registration by a shared password or by allowed email domains.';
$string['setting:quickreggatepassword'] = 'Shared password';
$string['setting:quickreggatepassword_desc'] = 'Applicants must enter this password. Stored only as a hash; leave empty to keep the current password.';
$string['setting:quickregmaxperip'] = 'Quick registrations per address';
$string['setting:quickregmaxperip_desc'] = 'Maximum quick registrations allowed from one client address within the window.';
$string['setting:quickregwindow'] = 'Quick-registration rate window (seconds)';
$string['setting:quickregwindow_desc'] = 'Length of the sliding window used for quick-registration rate limiting.';
$string['setting:tempmaxperip'] = 'Temporary creations per address';
$string['setting:tempmaxperip_desc'] = 'Maximum anonymous temporary accounts one client address may create within the window.';
$string['setting:tempsitemax'] = 'Site-wide temporary-creation limit';
$string['setting:tempsitemax_desc'] = 'Maximum anonymous temporary accounts created site-wide within the site window (circuit breaker). Set to 0 to disable.';
$string['setting:tempsitewindow'] = 'Site-wide rate window (seconds)';
$string['setting:tempsitewindow_desc'] = 'Length of the sliding window for the site-wide temporary-creation circuit breaker.';
$string['setting:tempwindow'] = 'Temporary-creation rate window (seconds)';
$string['setting:tempwindow_desc'] = 'Length of the sliding window used for temporary-account creation limits.';
$string['settings:access'] = 'Access window and capacity';
$string['settings:accesskey'] = 'Temporary-user access key';
$string['settings:accesskeygate'] = 'Access key';
$string['settings:defaults'] = 'Policy defaults';
$string['settings:lifecycle'] = 'Lifetimes and expiry';
$string['settings:methods'] = 'Access methods';
$string['settings:quickreggate'] = 'Quick-registration access gate';
$string['settings:quickreggate_desc'] = 'Optional additional restriction for public quick registration, on top of email activation. Course settings override these defaults.';
$string['settings:ratelimit'] = 'Rate limiting';
$string['settings:ratelimit_desc'] = 'Abuse protection for public quick registration. Defaults are NAT-friendly so a shared class address is not blocked.';
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
