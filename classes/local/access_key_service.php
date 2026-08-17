<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Temporary-user access-key policy and verification boundary.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess\local;

/** Verifies shared access keys without exposing persisted hashes to callers. */
final class access_key_service {
    /**
     * Verify a clear-text candidate against a stored hash.
     *
     * This low-level helper exists for unit testing and internal use. Public cross-plugin APIs must resolve the
     * effective system/course hash internally and return only a boolean result.
     *
     * @param string $candidate Candidate supplied by the user.
     * @param string|null $hash Persisted password hash.
     * @return bool
     */
    public static function verify_candidate(string $candidate, ?string $hash): bool {
        if ($candidate === '' || $hash === null || $hash === '') {
            return false;
        }
        return password_verify($candidate, $hash);
    }
}
