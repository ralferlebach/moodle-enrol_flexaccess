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
 * Administration settings for enrol_flexaccess.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('enrol_flexaccess/defaults', get_string('settings:defaults', 'enrol_flexaccess'), ''));
    $settings->add(new admin_setting_configselect(
        'enrol_flexaccess/participantvisibilitydefault',
        get_string('participantvisibilitydefault', 'enrol_flexaccess'),
        get_string('participantvisibilitydefault_desc', 'enrol_flexaccess'),
        'show',
        ['show' => get_string('show', 'enrol_flexaccess'), 'hide' => get_string('hide', 'enrol_flexaccess')]
    ));
    $settings->add(new admin_setting_configcheckbox(
        'enrol_flexaccess/allowwidening',
        get_string('allowwidening', 'enrol_flexaccess'),
        get_string('allowwidening_desc', 'enrol_flexaccess'),
        0
    ));
    $settings->add(new admin_setting_heading(
        'enrol_flexaccess/accesskey',
        get_string('settings:accesskey', 'enrol_flexaccess'),
        ''
    ));
    $settings->add(new admin_setting_configcheckbox(
        'enrol_flexaccess/temporaryaccesskeyrequired',
        get_string('temporaryaccesskeyrequired', 'enrol_flexaccess'),
        get_string('temporaryaccesskeyrequired_desc', 'enrol_flexaccess'),
        0
    ));
    $settings->add(new \enrol_flexaccess\admin\setting_access_key(
        'enrol_flexaccess/temporaryaccesskeyhash',
        get_string('temporaryaccesskey', 'enrol_flexaccess'),
        get_string('temporaryaccesskey_desc', 'enrol_flexaccess'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_heading(
        'enrol_flexaccess/quickreggate',
        get_string('settings:quickreggate', 'enrol_flexaccess'),
        get_string('settings:quickreggate_desc', 'enrol_flexaccess')
    ));
    $settings->add(new admin_setting_configselect(
        'enrol_flexaccess/quickreggatemode',
        get_string('setting:quickreggatemode', 'enrol_flexaccess'),
        get_string('setting:quickreggatemode_desc', 'enrol_flexaccess'),
        'none',
        [
            'none' => get_string('gate:none', 'enrol_flexaccess'),
            'password' => get_string('gate:password', 'enrol_flexaccess'),
            'domain' => get_string('gate:domain', 'enrol_flexaccess'),
        ]
    ));
    $settings->add(new \enrol_flexaccess\admin\setting_access_key(
        'enrol_flexaccess/quickreggatepasswordhash',
        get_string('setting:quickreggatepassword', 'enrol_flexaccess'),
        get_string('setting:quickreggatepassword_desc', 'enrol_flexaccess'),
        '',
        PARAM_RAW_TRIMMED
    ));
    $settings->add(new admin_setting_configtextarea(
        'enrol_flexaccess/quickreggatedomains',
        get_string('setting:quickreggatedomains', 'enrol_flexaccess'),
        get_string('setting:quickreggatedomains_desc', 'enrol_flexaccess'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_heading(
        'enrol_flexaccess/ratelimit',
        get_string('settings:ratelimit', 'enrol_flexaccess'),
        get_string('settings:ratelimit_desc', 'enrol_flexaccess')
    ));
    $settings->add(new admin_setting_configtext(
        'enrol_flexaccess/quickregmaxperip',
        get_string('setting:quickregmaxperip', 'enrol_flexaccess'),
        get_string('setting:quickregmaxperip_desc', 'enrol_flexaccess'),
        30,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'enrol_flexaccess/quickregwindow',
        get_string('setting:quickregwindow', 'enrol_flexaccess'),
        get_string('setting:quickregwindow_desc', 'enrol_flexaccess'),
        600,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_flexaccess/tempmaxperip',
        get_string('setting:tempmaxperip', 'enrol_flexaccess'),
        get_string('setting:tempmaxperip_desc', 'enrol_flexaccess'),
        30,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'enrol_flexaccess/tempwindow',
        get_string('setting:tempwindow', 'enrol_flexaccess'),
        get_string('setting:tempwindow_desc', 'enrol_flexaccess'),
        600,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'enrol_flexaccess/tempsitemax',
        get_string('setting:tempsitemax', 'enrol_flexaccess'),
        get_string('setting:tempsitemax_desc', 'enrol_flexaccess'),
        0,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'enrol_flexaccess/tempsitewindow',
        get_string('setting:tempsitewindow', 'enrol_flexaccess'),
        get_string('setting:tempsitewindow_desc', 'enrol_flexaccess'),
        3600,
        PARAM_INT
    ));
}
