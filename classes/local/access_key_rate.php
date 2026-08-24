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

namespace enrol_flexaccess\local;

/**
 * Brute-force limiter for anonymous access-key attempts.
 *
 * Counts failed attempts per opaque identifier (a hash of client address and course, never the key
 * itself) within a sliding window and blocks further attempts once the threshold is reached. Backed
 * by the application cache so the limit holds across the anonymous, session-less entry requests.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access_key_rate {
    /** @var int Maximum failed attempts allowed within the window. */
    const MAX_ATTEMPTS = 5;

    /** @var int Sliding window length in seconds. */
    const WINDOW = 300;

    /**
     * Build an opaque identifier for a client and course. The key is never part of it.
     *
     * @param string $client Client address (or other stable per-visitor token).
     * @param int $courseid Course id.
     * @return string
     */
    public static function identifier(string $client, int $courseid): string {
        return sha1($client . '|' . $courseid);
    }

    /**
     * Whether the identifier is currently blocked (too many recent failures).
     *
     * @param string $identifier Opaque identifier from {@see self::identifier()}.
     * @param int|null $now Current time.
     * @return bool
     */
    public static function is_blocked(string $identifier, ?int $now = null): bool {
        $now = $now ?? time();
        $entry = self::cache()->get($identifier);
        if (!is_array($entry)) {
            return false;
        }
        if ($now - (int) $entry['since'] > self::WINDOW) {
            return false;
        }
        return (int) $entry['count'] >= self::MAX_ATTEMPTS;
    }

    /**
     * Record a failed attempt for the identifier.
     *
     * @param string $identifier Opaque identifier.
     * @param int|null $now Current time.
     * @return void
     */
    public static function record_failure(string $identifier, ?int $now = null): void {
        $now = $now ?? time();
        $cache = self::cache();
        $entry = $cache->get($identifier);
        if (!is_array($entry) || $now - (int) $entry['since'] > self::WINDOW) {
            $entry = ['count' => 0, 'since' => $now];
        }
        $entry['count'] = (int) $entry['count'] + 1;
        $cache->set($identifier, $entry);
    }

    /**
     * Clear the failure counter for the identifier (e.g. after a successful grant).
     *
     * @param string $identifier Opaque identifier.
     * @return void
     */
    public static function reset(string $identifier): void {
        self::cache()->delete($identifier);
    }

    /**
     * The ad-hoc application cache used to hold attempt counters.
     *
     * @return \cache
     */
    private static function cache(): \cache {
        return \cache::make_from_params(
            \cache_store::MODE_APPLICATION,
            'enrol_flexaccess',
            'accesskeyrate'
        );
    }
}
