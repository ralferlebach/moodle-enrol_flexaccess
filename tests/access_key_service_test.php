<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests for the temporary-user access-key verification boundary.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

/** Access-key service tests. */
final class access_key_service_test extends \advanced_testcase {
    /** Verify matching and non-matching candidates. */
    public function test_verify_candidate(): void {
        $hash = password_hash('Event-Key-2026', PASSWORD_DEFAULT);
        $this->assertIsString($hash);
        $this->assertTrue(\enrol_flexaccess\local\access_key_service::verify_candidate('Event-Key-2026', $hash));
        $this->assertFalse(\enrol_flexaccess\local\access_key_service::verify_candidate('wrong', $hash));
        $this->assertFalse(\enrol_flexaccess\local\access_key_service::verify_candidate('', $hash));
        $this->assertFalse(\enrol_flexaccess\local\access_key_service::verify_candidate('Event-Key-2026', null));
    }
}
