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
 * Applies enrolment expiry for FlexAccess enrolments whose end time has passed.
 *
 * Enrolment lifetime (enrolperiod) is independent of account lifetime: a course enrolment can end
 * while the account lives on, or vice versa. When an enrolment's end time passes, this service
 * applies the owning instance's configured action — suspend the enrolment or unenrol the user.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrol_expiry {
    /**
     * Suspend or unenrol active FlexAccess enrolments whose end time has passed.
     *
     * @param int|null $now Current time.
     * @param int $limit Maximum number of enrolments to process in one run.
     * @return int Number of enrolments processed.
     */
    public static function process(?int $now = null, int $limit = 1000): int {
        global $DB;
        $now = $now ?? time();
        $plugin = enrol_get_plugin('flexaccess');
        if (!$plugin) {
            return 0;
        }

        $sql = "SELECT ue.id AS ueid, ue.userid, ue.enrolid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'flexaccess'
                 WHERE ue.timeend > 0 AND ue.timeend <= :now AND ue.status = :active
              ORDER BY ue.timeend ASC";
        $rows = $DB->get_records_sql($sql, ['now' => $now, 'active' => ENROL_USER_ACTIVE], 0, $limit);

        $instances = [];
        $actions = [];
        $processed = 0;
        foreach ($rows as $row) {
            $enrolid = (int) $row->enrolid;
            if (!isset($instances[$enrolid])) {
                $instances[$enrolid] = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
                $config = instance_config::load($enrolid);
                $actions[$enrolid] = ($config && $config->expiryaction === 'unenrol') ? 'unenrol' : 'suspend';
            }
            if ($actions[$enrolid] === 'unenrol') {
                $plugin->unenrol_user($instances[$enrolid], (int) $row->userid);
            } else {
                $plugin->update_user_enrol($instances[$enrolid], (int) $row->userid, ENROL_USER_SUSPENDED);
            }
            $processed++;
        }
        return $processed;
    }
}
