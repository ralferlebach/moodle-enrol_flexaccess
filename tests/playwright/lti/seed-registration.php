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
 * Seed of the LTI provider site, step 3 of 3: complete the platform registration and add the deployment.
 *
 * Expects the platform's values in the environment (from seed-platform.php).
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../../config.php');

use enrol_lti\local\ltiadvantage\entity\application_registration;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;

$env = static function (string $name): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        cli_error("Missing environment variable $name.");
    }
    return $value;
};

$repository = new application_registration_repository();
$draft = $repository->find_by_uniqueid($env('LTI_PROVIDER_REGISTRATION'));
if (!$draft) {
    cli_error('Run seed-provider.php first.');
}
$registration = $repository->save(application_registration::create(
    $draft->get_name(),
    $draft->get_uniqueid(),
    new moodle_url($env('LTI_PLATFORM_ISSUER')),
    $env('LTI_PLATFORM_CLIENTID'),
    new moodle_url($env('LTI_PLATFORM_AUTH_URL')),
    new moodle_url($env('LTI_PLATFORM_JWKS_URL')),
    new moodle_url($env('LTI_PLATFORM_TOKEN_URL')),
    $draft->get_id()
));
$deployments = new deployment_repository();
if (!$deployments->find_by_registration($registration->get_id(), $env('LTI_PLATFORM_DEPLOYMENTID'))) {
    $deployments->save($registration->add_tool_deployment('FlexAccess platform', $env('LTI_PLATFORM_DEPLOYMENTID')));
}
echo "export LTI_REGISTRATION_COMPLETE='1'\n";
