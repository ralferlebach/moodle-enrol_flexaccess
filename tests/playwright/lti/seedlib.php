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
 * Shared helpers of the two-site LTI 1.3 seed scripts.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Enable both halves of FlexAccess and the given further auth plugins; immediate persistence.
 *
 * @param string[] $auths Further auth plugins to enable.
 * @return void
 */
function flexaccess_lti_seed_site_settings(array $auths = []): void {
    set_config('allowwidening', 1, 'enrol_flexaccess');
    set_config('requireemailverification', 0, 'auth_flexaccess');
    $enabled = get_enabled_auth_plugins();
    foreach (array_merge(['flexaccess'], $auths) as $auth) {
        if (!in_array($auth, $enabled, true)) {
            $enabled[] = $auth;
        }
    }
    set_config('auth', implode(',', array_diff($enabled, ['manual', 'nologin'])));
    \core\session\manager::gc();
}

/**
 * A permanent FlexAccess account (own password, ACTIVE).
 *
 * @param string $username Username.
 * @param string $firstname First name.
 * @param string $lastname Last name.
 * @param string $email E-mail.
 * @param string $password Password.
 * @return int User id.
 */
function flexaccess_lti_seed_durable_user(
    string $username,
    string $firstname,
    string $lastname,
    string $email,
    string $password
): int {
    global $CFG, $DB;
    $existing = $DB->get_record('user', ['username' => $username]);
    $record = (object) [
        'username' => $username,
        'auth' => 'flexaccess',
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $email,
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
    ];
    if ($existing) {
        $userid = (int) $existing->id;
    } else if (method_exists(\core\user::class, 'create_user')) {
        // Moodle 5.3 deprecates user_create_user() in favour of \core\user::create_user().
        $userid = (int) \core\user::create_user($record, false, false);
    } else {
        $userid = (int) user_create_user($record, false, false);
    }
    update_internal_user_password(\core_user::get_user($userid), $password);
    if (!$DB->record_exists('auth_flexaccess_account', ['userid' => $userid])) {
        \auth_flexaccess\local\account_service::create_authenticated(
            $userid,
            \auth_flexaccess\local\account_service::generate_unique_reference()
        );
    }
    return $userid;
}

/**
 * Print shell export lines.
 *
 * @param array $values Name => value.
 * @return void
 */
function flexaccess_lti_seed_export(array $values): void {
    foreach ($values as $name => $value) {
        echo 'export ' . $name . "='" . str_replace("'", '', (string) $value) . "'\n";
    }
}
