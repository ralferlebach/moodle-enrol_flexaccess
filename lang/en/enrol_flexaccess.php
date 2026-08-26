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

$string['accessenter'] = 'Enter course';
$string['accessenterintro'] = 'This course uses FlexAccess. Enter it with your existing account:';
$string['accesskeyinvalid'] = 'The access key is not valid.';
$string['accesskeymodecourse'] = 'Use a course-specific access key';
$string['accesskeymodeinherit'] = 'Inherit system setting';
$string['accesslists'] = 'Anonymous access lists';
$string['accessnotopen'] = 'FlexAccess access is not available at this time.';
$string['allowguest'] = 'Allow normal guest access';
$string['allowguest_help'] = 'Offer Moodle guest access as one of the FlexAccess entry options.';
$string['allowmagiclogin'] = 'Allow email-link login';
$string['allowmagiclogin_help'] = 'When enabled, visitors can request a one-time login link by email instead of entering a password. Requires the email-link login to be enabled site-wide in the FlexAccess authentication settings.';
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
$string['checkcoupling'] = 'FlexAccess auth/enrol coupling';
$string['checkcouplingaction'] = 'Manage enrol plugins';
$string['checkcouplingauthonly'] = 'auth_flexaccess is enabled but enrol_flexaccess is not';
$string['checkcouplingauthonly_detail'] = 'The authentication plugin is on, but no course can offer FlexAccess entry because the enrolment plugin is disabled. Enable enrol_flexaccess, or disable auth_flexaccess if FlexAccess is not in use.';
$string['checkcouplingbothoff'] = 'FlexAccess is not enabled.';
$string['checkcouplingenrolonly'] = 'enrol_flexaccess is enabled but auth_flexaccess is not';
$string['checkcouplingenrolonly_detail'] = 'The enrolment plugin provisions temporary and quick-registered accounts that authenticate through auth_flexaccess. With that authentication plugin disabled, those accounts cannot log in. Enable auth_flexaccess.';
$string['checkcouplingok'] = 'auth_flexaccess and enrol_flexaccess are both enabled.';
$string['coursefull'] = 'The maximum number of participants for this access method has been reached.';
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_help'] = 'How long a new enrolment stays active, independent of the temporary account lifetime. When set, the enrolment ends after this period and the expiry action is applied. Leave at zero to keep the enrolment until the account expires or it is removed manually.';
$string['errorcapacitylock'] = 'Could not acquire the capacity lock. Please try again.';
$string['errormaxparticipants'] = 'The maximum number of participants must be 0 (unlimited) or a positive number.';
$string['errorwindowrange'] = 'The "available until" time must be after the "available from" time.';
$string['expiryaction'] = 'Expiry action';
$string['expiryactionsuspend'] = 'Suspend the enrolment';
$string['expiryactionunenrol'] = 'Unenrol the user';
$string['expiryaction_help'] = 'What happens to a FlexAccess enrolment when it expires.';
$string['flexaccess:config'] = 'Configure FlexAccess enrolment instances';
$string['flexaccess:unenrol'] = 'Unenrol users from a FlexAccess instance';
$string['gatedomain'] = 'Allowed email domains';
$string['gateinherit'] = 'Inherit site default';
$string['gatenone'] = 'No additional gate';
$string['gatepassword'] = 'Shared password';
$string['hide'] = 'Hide';
$string['instancequickreggatedomains'] = 'Allowed email domains';
$string['instancequickreggatemode'] = 'Quick-registration gate';
$string['instancequickreggatepassword'] = 'Gate password';
$string['maxparticipants'] = 'Maximum participants';
$string['maxparticipants_help'] = 'Maximum number of active FlexAccess enrolments for this instance. 0 means unlimited. Expired access frees capacity. There is no waitlist.';
$string['methodneutralised'] = 'Your selection has no effect here: the following access methods are switched off at a higher level (a system-wide or course-category default) and cannot be enabled here while \'Allow widening by lower levels\' is off there: {$a}. To allow them, an administrator must enable the method - or \'Allow widening by lower levels\' - under Site administration > Plugins > Enrolments > FlexAccess, or in the course category policy.';
$string['modeallowguest'] = 'guest';
$string['modeallowmagiclogin'] = 'email link';
$string['modeallownormallogin'] = 'login';
$string['modeallowquick'] = 'quick registration';
$string['modeallowtemporary'] = 'temporary';
$string['modelabel'] = 'Active access modes';
$string['modenone'] = 'no anonymous access';
$string['participantrole'] = 'FlexAccess participant';
$string['participantrole_desc'] = 'Dedicated role assigned to temporary and quick-registered FlexAccess visitors. It mirrors the student role but lets a course hide its participant list from these visitors.';
$string['participantlistaccess'] = 'Participant-list access for temporary visitors';
$string['participantlistaccessinherit'] = 'Inherit site default';
$string['participantlistaccess_help'] = 'Controls whether temporary and quick-registered visitors of this course may open the course participant list. "Deny" prevents them from viewing the participants page; "Inherit" uses the site default. Note: this does NOT hide temporary visitors from the participant list shown to other users — Moodle offers no stable extension point for that, so that feature is not provided.';
$string['participantlistaccessdefault'] = 'Default participant-list access for temporary visitors';
$string['participantlistaccessdefault_desc'] = 'System default for whether temporary visitors may view the participant list. Each FlexAccess enrolment instance can inherit, allow or deny.';
$string['pluginname'] = 'FlexAccess enrolment';
$string['privacy:metadata'] = 'FlexAccess enrolment stores policy and enrolment configuration only; user enrolments are held by Moodle core.';
$string['restrictionrole'] = 'FlexAccess restricted visitor';
$string['restrictionrole_desc'] = 'System-level role assigned to temporary FlexAccess visitors that only withdraws messaging and profile-editing capabilities. It grants nothing and is removed on conversion to a full account.';
$string['restrictionsadd'] = 'Add restriction';
$string['restrictionsadded'] = 'Restriction added.';
$string['restrictionscohorthint'] = 'Cohort (used when type is Cohort)';
$string['restrictionsdeleted'] = 'Restriction deleted.';
$string['restrictionseffect'] = 'Effect';
$string['restrictionseffectallow'] = 'Allow';
$string['restrictionseffectdeny'] = 'Deny';
$string['restrictionsintro'] = 'Restrict who may use FlexAccess in this course. Without any rule, everyone may use it. A deny rule always wins; if at least one allow rule exists, only users matching an allow rule may use FlexAccess. Rules set site-wide or for the course category also apply here.';
$string['restrictionsinvalid'] = 'The restriction could not be added: please choose a valid role or cohort.';
$string['restrictionskind'] = 'Type';
$string['restrictionskindcohort'] = 'Cohort';
$string['restrictionskindrole'] = 'Role';
$string['restrictionsmanage'] = 'Manage role/cohort restrictions';
$string['restrictionsmissingref'] = '(deleted)';
$string['restrictionsnone'] = 'No restrictions are defined for this course, so FlexAccess is available to everyone (unless a site or category rule applies).';
$string['restrictionsreference'] = 'Role or cohort';
$string['restrictionsrole'] = 'Role';
$string['restrictionstitle'] = 'FlexAccess role and cohort restrictions';
$string['settingallowguest'] = 'Default: allow normal guest access';
$string['settingallowguest_desc'] = 'System default ceiling for Moodle guest access as a FlexAccess entry option. Instances may narrow this.';
$string['settingallowmagiclogin'] = 'Default: allow email-link login';
$string['settingallowmagiclogin_desc'] = 'System-wide ceiling for offering the email-link (magic) login. Instances may only narrow this.';
$string['settingallownormallogin'] = 'Default: allow normal login';
$string['settingallownormallogin_desc'] = 'System default ceiling for keeping the standard Moodle login available alongside FlexAccess methods.';
$string['settingallowquick'] = 'Default: allow quick registration';
$string['settingallowquick_desc'] = 'System default ceiling for quick registration. Must be on here (or widening enabled) for an instance checkbox to take effect.';
$string['settingallowtemporary'] = 'Default: allow temporary users';
$string['settingallowtemporary_desc'] = 'System default ceiling for temporary anonymous access. Must be on here (or widening enabled) for an instance checkbox to take effect.';
$string['settingquickreggatedomains'] = 'Allowed email domains';
$string['settingquickreggatedomains_desc'] = 'One domain per line (or comma separated), e.g. university.edu. Subdomains are accepted.';
$string['settingquickreggatemode'] = 'Gate type';
$string['settingquickreggatemode_desc'] = 'Restrict quick registration by a shared password or by allowed email domains.';
$string['settingquickreggatepassword'] = 'Shared password';
$string['settingquickreggatepassword_desc'] = 'Applicants must enter this password. Stored only as a hash; leave empty to keep the current password.';
$string['settingquickregmaxperip'] = 'Quick registrations per address';
$string['settingquickregmaxperip_desc'] = 'Maximum quick registrations allowed from one client address within the window.';
$string['settingquickregwindow'] = 'Quick-registration rate window (seconds)';
$string['settingquickregwindow_desc'] = 'Length of the sliding window used for quick-registration rate limiting.';
$string['settingtempmaxperip'] = 'Temporary creations per address';
$string['settingtempmaxperip_desc'] = 'Maximum anonymous temporary accounts one client address may create within the window.';
$string['settingtempsitemax'] = 'Site-wide temporary-creation limit';
$string['settingtempsitemax_desc'] = 'Maximum anonymous temporary accounts created site-wide within the site window (circuit breaker). Set to 0 to disable.';
$string['settingtempsitewindow'] = 'Site-wide rate window (seconds)';
$string['settingtempsitewindow_desc'] = 'Length of the sliding window for the site-wide temporary-creation circuit breaker.';
$string['settingtempwindow'] = 'Temporary-creation rate window (seconds)';
$string['settingtempwindow_desc'] = 'Length of the sliding window used for temporary-account creation limits.';
$string['settingsaccess'] = 'Access window and capacity';
$string['settingsaccesskey'] = 'Temporary-user access key';
$string['settingsaccesskeygate'] = 'Access key';
$string['settingsdefaults'] = 'Policy defaults';
$string['settingslifecycle'] = 'Lifetimes and expiry';
$string['settingsmethods'] = 'Access methods';
$string['settingsquickreggate'] = 'Quick-registration access gate';
$string['settingsquickreggate_desc'] = 'Optional additional restriction for public quick registration, on top of email activation. Course settings override these defaults.';
$string['settingsratelimit'] = 'Rate limiting';
$string['settingsratelimit_desc'] = 'Abuse protection for public quick registration. Defaults are NAT-friendly so a shared class address is not blocked.';
$string['show'] = 'Show';
$string['status'] = 'Enable FlexAccess enrolment';
$string['taskexpireenrolments'] = 'Expire FlexAccess course enrolments';
$string['temporaryaccesskey'] = 'System-wide access key';
$string['temporaryaccesskey_desc'] = 'Enter a new shared access key. Only a secure hash is stored. Leave blank to retain the current key; use the separate setting to enable or disable the requirement.';
$string['temporaryaccesskey_help'] = 'The shared key a visitor must enter for temporary access. Leave blank to keep the current key.';
$string['temporaryaccesskeymode'] = 'Access key mode';
$string['temporaryaccesskeymodecourse'] = 'Use a course access key';
$string['temporaryaccesskeymodeinherit'] = 'Inherit site setting';
$string['temporaryaccesskeymode_help'] = 'Whether temporary entry uses no course key (inherit the site setting) or a key specific to this course.';
$string['temporaryaccesskeyrequired'] = 'Require a system-wide access key';
$string['temporaryaccesskeyrequired_desc'] = 'Requires an access key system-wide for FlexAccess modes that create a temporary user. Courses may inherit it or replace it with their own key.';
$string['temporarylifetime'] = 'Temporary account lifetime';
$string['temporarylifetime_help'] = 'How long a temporary account remains active before it expires.';
