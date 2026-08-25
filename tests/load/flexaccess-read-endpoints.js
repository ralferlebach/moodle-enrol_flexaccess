// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * k6 read-endpoint plateau load test for enrol_flexaccess (AP6) - the k6 twin of
 * flexaccess-read-endpoints.jmx. It keeps VUS virtual users concurrently active for
 * DURATION, repeatedly requesting the anonymous access entry and the magic-login
 * endpoint and asserting they stay healthy.
 *
 * Environment (exported by tests/load/seed_large.php): BASE_URL, COURSEID.
 * Tuning: VUS (default 25), DURATION (default 90s). Thresholds below are an initial
 * baseline - tune after the first real run, exactly like the JMeter assertions.
 */
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL;
const COURSEID = __ENV.COURSEID;

export const options = {
  scenarios: {
    plateau: {
      executor: 'constant-vus',
      vus: Number(__ENV.VUS || 25),
      duration: __ENV.DURATION || '90s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<2000'],
  },
};

export default function () {
  // Anonymous access entry page (target-aware); the dominant read path.
  const entry = http.get(`${BASE_URL}/auth/flexaccess/access.php?courseid=${COURSEID}`, {
    tags: { endpoint: 'access' },
  });
  check(entry, { 'access.php returns 200': (r) => r.status === 200 });

  // Magic-login endpoint: a bare GET without a token must fail closed, not error out.
  const magic = http.get(`${BASE_URL}/auth/flexaccess/magic.php`, {
    tags: { endpoint: 'magic' },
    redirects: 0,
  });
  check(magic, { 'magic.php reachable': (r) => r.status === 200 || r.status === 303 });

  sleep(1);
}
