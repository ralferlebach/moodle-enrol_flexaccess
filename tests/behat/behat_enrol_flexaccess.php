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
 * Behat step definitions for FlexAccess ecosystem (cross-plugin) tests.
 *
 * These steps deliberately drive the real cross-plugin flow (enrol_flexaccess creating an account
 * through auth_flexaccess and enrolling it), so they only work when the sibling plugins are
 * installed. Ecosystem CI installs them via moodle-plugin-ci --extra-plugins.
 *
 * @package    enrol_flexaccess
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Cross-plugin FlexAccess step definitions.
 *
 * @package    enrol_flexaccess
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_enrol_flexaccess extends behat_base {
    /**
     * Grants a temporary FlexAccess account in a course via the real enrol access flow.
     *
     * @Given a FlexAccess temporary account is granted in course :coursefullname
     * @param string $coursefullname Full name of an existing course.
     * @return void
     */
    public function a_flexaccess_temporary_account_is_granted_in_course(string $coursefullname): void {
        global $DB;

        $courseid = (int) $DB->get_field('course', 'id', ['fullname' => $coursefullname], MUST_EXIST);
        $course = get_course($courseid);

        // The instance-level allowtemporary flag may only widen the category policy when widening is on.
        set_config('allowwidening', 1, 'enrol_flexaccess');

        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        $DB->set_field('enrol_flexaccess_instance', 'allowtemporary', 1, ['enrolid' => $enrolid]);
        $DB->set_field('enrol_flexaccess_instance', 'temporarylifetime', DAYSECS, ['enrolid' => $enrolid]);

        $result = \enrol_flexaccess\local\access_controller::grant_temporary_access($courseid);
        if ($result->status !== 'granted') {
            throw new \Exception('FlexAccess temporary access was not granted: ' . $result->status);
        }
    }

    /**
     * Configures a course with an enabled FlexAccess method that offers temporary access.
     *
     * @Given a FlexAccess enrolment method allowing temporary access exists in course :coursefullname
     * @param string $coursefullname Full name of an existing course.
     * @return void
     */
    public function a_flexaccess_method_allowing_temporary_access_exists_in_course(string $coursefullname): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['fullname' => $coursefullname], MUST_EXIST);
        $course = get_course($courseid);

        set_config('allowwidening', 1, 'enrol_flexaccess');
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        \enrol_flexaccess\local\instance_config::save($enrolid, [
            'allowtemporary' => 1,
            'temporarylifetime' => DAYSECS,
        ]);
    }

    /**
     * Opens the anonymous FlexAccess entry page for a course.
     *
     * @When I open the FlexAccess entry page for course :coursefullname
     * @param string $coursefullname Full name of an existing course.
     * @return void
     */
    public function i_open_the_flexaccess_entry_page_for_course(string $coursefullname): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['fullname' => $coursefullname], MUST_EXIST);
        $url = new \moodle_url('/auth/flexaccess/access.php', ['courseid' => $courseid]);
        $this->execute('behat_general::i_visit', [$url]);
    }

    /**
     * Configures a course with a FlexAccess method offering quick registration, and enables the
     * FlexAccess authentication method so registered accounts can log in again.
     *
     * @Given a FlexAccess enrolment method allowing quick registration exists in course :coursefullname
     * @param string $coursefullname Full name of an existing course.
     * @return void
     */
    public function a_flexaccess_method_allowing_quick_registration_exists_in_course(string $coursefullname): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['fullname' => $coursefullname], MUST_EXIST);
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance(get_course($courseid), ['status' => ENROL_INSTANCE_ENABLED]);
        \enrol_flexaccess\local\instance_config::save($enrolid, ['allowquick' => 1]);

        $enabled = get_enabled_auth_plugins();
        if (!in_array('flexaccess', $enabled, true)) {
            $enabled[] = 'flexaccess';
            set_config('auth', implode(',', $enabled));
        }
    }

    /**
     * Configures a course whose FlexAccess method offers guest access and normal login, and enables
     * Moodle guest enrolment on the course so the guest link can actually enter.
     *
     * @Given a FlexAccess enrolment method offering guest access and normal login exists in course :coursefullname
     * @param string $coursefullname Full name of an existing course.
     * @return void
     */
    public function a_flexaccess_method_offering_guest_access_and_normal_login_exists_in_course(
        string $coursefullname
    ): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['fullname' => $coursefullname], MUST_EXIST);
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance(get_course($courseid), ['status' => ENROL_INSTANCE_ENABLED]);
        \enrol_flexaccess\local\instance_config::save($enrolid, [
            'allowtemporary' => 1,
            'allowguest' => 1,
            'allownormallogin' => 1,
        ]);

        $guest = enrol_get_plugin('guest');
        if ($guest) {
            $guest->add_instance(get_course($courseid), ['status' => ENROL_INSTANCE_ENABLED]);
        }
    }

    /**
     * Creates a permanent (authenticated, active) FlexAccess account and enables the auth method.
     *
     * @Given a permanent FlexAccess account :email exists with name :firstname :lastname
     * @param string $email Email address (also the username).
     * @param string $firstname First name.
     * @param string $lastname Last name.
     * @return void
     */
    public function a_permanent_flexaccess_account_exists_with_name(
        string $email,
        string $firstname,
        string $lastname
    ): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        $user = (object) [
            'auth' => 'flexaccess',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => $email,
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'emailstop' => 0,
        ];
        if (method_exists(\core\user::class, 'create_user')) {
            $userid = (int) \core\user::create_user($user, false, true);
        } else {
            $userid = (int) user_create_user($user, false, true);
        }
        \auth_flexaccess\local\account_service::create_authenticated($userid, '5555555555');

        $enabled = get_enabled_auth_plugins();
        if (!in_array('flexaccess', $enabled, true)) {
            $enabled[] = 'flexaccess';
            set_config('auth', implode(',', $enabled));
        }
    }

    /**
     * Opens the FlexAccess magic-login request page.
     *
     * @When I open the FlexAccess magic-login page
     * @return void
     */
    public function i_open_the_flexaccess_magic_login_page(): void {
        $this->execute('behat_general::i_visit', [new \moodle_url('/auth/flexaccess/magic.php')]);
    }

    /**
     * Issues a magic-login token for a user and opens the resulting link.
     *
     * @When I open a FlexAccess magic-login link for :email
     * @param string $email Email address of an existing account.
     * @return void
     */
    public function i_open_a_flexaccess_magic_login_link_for(string $email): void {
        global $DB;
        $userid = (int) $DB->get_field('user', 'id', ['email' => $email], MUST_EXIST);
        $token = \auth_flexaccess\local\token_service::issue($userid, 'magiclogin', 900);
        $url = new \moodle_url('/auth/flexaccess/magic.php', ['token' => $token]);
        $this->execute('behat_general::i_visit', [$url]);
    }

    /**
     * Enables the FlexAccess authentication method so its accounts can log in.
     *
     * @Given the FlexAccess authentication method is enabled
     * @return void
     */
    public function the_flexaccess_authentication_method_is_enabled(): void {
        $enabled = get_enabled_auth_plugins();
        if (!in_array('flexaccess', $enabled, true)) {
            $enabled[] = 'flexaccess';
            set_config('auth', implode(',', $enabled));
        }
    }

    /**
     * Opens the FlexAccess persistence page for the current user.
     *
     * @When I open the FlexAccess persistence page
     * @return void
     */
    public function i_open_the_flexaccess_persistence_page(): void {
        $this->execute('behat_general::i_visit', [new \moodle_url('/auth/flexaccess/persist.php')]);
    }

    /**
     * Opens the site login page directly.
     *
     * @When I open the site login page
     * @return void
     */
    public function i_open_the_site_login_page(): void {
        $this->execute('behat_general::i_visit', [new \moodle_url('/login/index.php')]);
    }

    /**
     * Opens the anonymous FlexAccess quick-registration page for a course.
     *
     * @When I open the FlexAccess quick registration page for course :coursefullname
     * @param string $coursefullname Full name of an existing course.
     * @return void
     */
    public function i_open_the_flexaccess_quick_registration_page_for_course(string $coursefullname): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['fullname' => $coursefullname], MUST_EXIST);
        $url = new \moodle_url('/auth/flexaccess/register.php', ['courseid' => $courseid]);
        $this->execute('behat_general::i_visit', [$url]);
    }

    /**
     * Configures a course whose temporary access is gated by a course access key.
     *
     * @Given a FlexAccess enrolment method requiring access key :key exists in course :coursefullname
     * @param string $key Clear-text access key.
     * @param string $coursefullname Full name of an existing course.
     * @return void
     */
    public function a_flexaccess_method_requiring_access_key_exists_in_course(
        string $key,
        string $coursefullname
    ): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['fullname' => $coursefullname], MUST_EXIST);
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $plugin = enrol_get_plugin('flexaccess');
        $enrolid = $plugin->add_instance(get_course($courseid), ['status' => ENROL_INSTANCE_ENABLED]);
        \enrol_flexaccess\local\instance_config::save($enrolid, [
            'allowtemporary' => 1,
            'temporarylifetime' => DAYSECS,
        ]);
        $DB->set_field('enrol_flexaccess_instance', 'temporaryaccesskeymode', 'course', ['enrolid' => $enrolid]);
        $DB->set_field(
            'enrol_flexaccess_instance',
            'temporaryaccesskeyhash',
            password_hash($key, PASSWORD_DEFAULT),
            ['enrolid' => $enrolid]
        );
    }
}
