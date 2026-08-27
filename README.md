moodle-enrol_flexaccess
=======================

[![Moodle Plugin CI](https://github.com/ralferlebach/moodle-enrol_flexaccess/actions/workflows/moodle-plugin-ci-main.yml/badge.svg?branch=main)](https://github.com/ralferlebach/moodle-enrol_flexaccess/actions?query=workflow%3A%22Moodle+Plugin+CI+Main%22+branch%3Amain)

FlexAccess enrolment decides who may enter a course and how: temporary access, quick registration, magic login, guest access, access keys, capacity limits and time windows - configurable per course, per course category and site-wide.

FlexAccess is not a single plugin but a set of four that work as one system. They are released
together, carry the same version number and declare each other as dependencies, so they can only be
installed and updated as a set.

* **auth_flexaccess** provides the identity layer: it creates the temporary accounts, converts them into permanent ones, issues one-time login links and runs the central, rate-limited mail queue that all four plugins send through.
* **enrol_flexaccess** decides who may enter a course and how: it owns the access policy across site, category and course, enforces capacity, access windows, access keys and role or cohort restrictions.
* **mod_flexaccess** is the in-course entry point for keeping an account: it lets a temporary visitor convert their own account into a permanent one at the point in the course the teacher chooses.
* **tool_flexaccess** is the operator's view: account overview, mail queue, site and category policies, invitations, campaigns and printable anonymous access lists.

This README documents **enrol_flexaccess** - the second bullet point above. The other three plugins are
documented in their own repositories.

Because the responsibilities are split this way, no rule exists twice: access is decided in one
place, identity is handled in another, and every mail leaves through one queue. That is also why a
partial installation does not work - a missing sibling means a missing part of the mechanism.


Requirements
------------

This plugin requires Moodle 4.5+

It also requires the other FlexAccess plugins. All four are released together and must be installed
in the same version (currently 1.0.0-RC1 / 2026082700):

* **auth_flexaccess (FlexAccess authentication)** - required dependency, declared in version.php\
  https://github.com/ralferlebach/moodle-auth_flexaccess
* **mod_flexaccess (FlexAccess activity)** - part of the same set; install it as well to use the complete feature range\
  https://github.com/ralferlebach/moodle-mod_flexaccess
* **tool_flexaccess (FlexAccess administration)** - part of the same set; install it as well to use the complete feature range\
  https://github.com/ralferlebach/moodle-tool_flexaccess


Motivation for this plugin
--------------------------

Whether a course may be entered without prior registration is a decision that belongs to the course, not to the site. At the same time, an institution needs to be able to set boundaries: not every category should be allowed to open its courses to anyone.

This plugin therefore implements a policy hierarchy. The site sets what is permitted at most, a category may narrow it further, and the course chooses within that frame. Capacity limits, access windows and access keys are part of the same policy, so a course owner can open a course without being able to circumvent institutional rules.


Installation
------------

Install the plugin like any other plugin to folder
/enrol/flexaccess

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins


Usage & Settings
----------------

After installing the plugin, it does not do anything to Moodle yet. Add the FlexAccess enrolment method to a course and choose the access methods that course should offer.

To configure the plugin and its behaviour, please visit:
Site administration -> Plugins -> Enrolments -> FlexAccess

There, you find settings for:

* **Permitted access methods** - which methods courses may offer at all (temporary access, quick registration, magic login, guest access, normal login).
* **Widening** - whether a course may go beyond the site default, or only narrow it.
* **Access keys** - whether a shared key is required, and the key itself.
* **Capacity and lifetime defaults** - the participant limit and how long temporary access lasts.
* **Participant list access** - whether temporary visitors may open the course participant list.

If you want to learn more about using enrolment plugins in Moodle, please see https://docs.moodle.org/en/Enrolment_methods.


Capabilities
------------

This plugin also introduces these additional capabilities:

* **enrol/flexaccess:config** - Configure the FlexAccess enrolment method in a course, including its role and cohort restrictions. By default, this is assigned to managers and editing teachers.
* **enrol/flexaccess:unenrol** - Remove FlexAccess enrolments from a course. By default, this is assigned to managers.


Scheduled Tasks
---------------

This plugin also introduces these additional scheduled tasks:

* **\enrol_flexaccess\task\expire_enrolments** - Ends FlexAccess enrolments whose access period has run out.\ By default, the task is enabled and runs hourly.


How this plugin works / Pitfalls
--------------------------------

Each access method is resolved through three levels: the site default, the category policy and the course instance. A method is offered only if every level allows it, unless widening is explicitly permitted. The result is what the entry page shows a visitor.

Capacity is enforced under a lock, so a course with one seat left grants it exactly once even when many people arrive at the same moment. Role and cohort restrictions are evaluated on top: a deny rule always wins, and as soon as one allow rule exists, only matching users may use FlexAccess. Restrictions can be evaluated site-wide, per category and per course; for this release only the course scope has an administration page.

**Pitfall:** guest access is only offered when the course really has a usable core guest enrolment. Enabling it in the FlexAccess policy alone is not enough.


Theme support
-------------

This plugin is developed and tested on Moodle Core's Boost theme.
It should also work with Boost child themes, including Moodle Core's Classic theme. However, we can't support any other theme than Boost.


Plugin repositories
-------------------

This plugin is not published in the Moodle plugins repository.

The latest development version can be found on Github:
https://github.com/ralferlebach/moodle-enrol_flexaccess


Bug and problem reports / Support requests
------------------------------------------

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.

Please report bugs and problems on Github:
https://github.com/ralferlebach/moodle-enrol_flexaccess/issues

We will do our best to solve your problems, but please note that due to limited resources we can't always provide per-case support.


Feature proposals
-----------------

Due to limited resources, the functionality of this plugin is primarily implemented for our own local needs and published as-is to the community. We are aware that members of the community will have other needs and would love to see them solved by this plugin.

Please issue feature proposals on Github:
https://github.com/ralferlebach/moodle-enrol_flexaccess/issues

Please create pull requests on Github:
https://github.com/ralferlebach/moodle-enrol_flexaccess/pulls

We are always interested to read about your feature proposals or even get a pull request from you, but please accept that we can handle your issues only as feature _proposals_ and not as feature _requests_.


Moodle release support
----------------------

Due to limited resources, this plugin is only maintained for the most recent major release of Moodle as well as the most recent LTS release of Moodle. Bugfixes are backported to the LTS release. However, new features and improvements are not necessarily backported to the LTS release.

Apart from these maintained releases, previous versions of this plugin which work in legacy major releases of Moodle are still available as-is without any further updates in the Moodle Plugins repository.

There may be several weeks after a new major release of Moodle has been published until we can do a compatibility check and fix problems if necessary. If you encounter problems with a new major release of Moodle - or can confirm that this plugin still works with a new major release - please let us know on Github.

This plugin is designed to be compatible with all currently supported versions of Moodle, leveraging its latest APIs. However, if you are using a legacy version of Moodle, we kindly advise against installing or using this plugin. Instead, we strongly recommend updating your Moodle instance to a supported version to ensure security and compliance with current technological standards. Thank you for your understanding.


Translating this plugin
-----------------------

This Moodle plugin is provided with English and German language packs only. Translations into other languages must be managed through AMOS (https://lang.moodle.org), where they will become part of Moodle's official language pack.

As the plugin creator, we continue to maintain the German translation. For all other languages, we kindly ask you to contribute your translations directly in AMOS. These contributions will be reviewed by Moodle's official language pack maintainers before being included in the official repository.

Thank you for supporting the global Moodle community!


Right-to-left support
---------------------

This plugin has not been tested with Moodle's support for right-to-left (RTL) languages.
If you want to use this plugin with a RTL language and it doesn't work as-is, you are free to send us a pull request on Github with modifications.


Maintainers
-----------

The plugin is maintained by\
Ralf Erlebach

Copyright
---------

The copyright of this plugin is held by\
Ralf Erlebach

Individual copyrights of individual developers are tracked in PHPDoc comments and Git commits.
