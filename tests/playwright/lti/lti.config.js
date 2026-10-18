/*
 * This file is part of Moodle - https://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
 */

/*
 * Playwright configuration of the two-site LTI 1.3 suite (platform + provider, see setup-sites.sh).
 *
 * The journeys depend on each other's order within a file (bindings are per platform identity), so
 * they run serially in one worker. Firefox runs the browser-specific journeys (@browser) only; set
 * LTI_BROWSERS=chromium to skip it where Firefox is not installed.
 */

const { defineConfig, devices } = require('@playwright/test');

const browsers = (process.env.LTI_BROWSERS || 'chromium,firefox').split(',');
const projects = [];
if (browsers.includes('chromium')) {
  projects.push({ name: 'chromium', use: { ...devices['Desktop Chrome'] } });
}
if (browsers.includes('firefox')) {
  projects.push({ name: 'firefox', grep: /@browser/, use: { ...devices['Desktop Firefox'] } });
}

module.exports = defineConfig({
  testDir: '.',
  testMatch: /.*\.spec\.js/,
  outputDir: 'test-results',
  timeout: 120 * 1000,
  expect: { timeout: 15 * 1000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  forbidOnly: !!process.env.CI,
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
  use: {
    ignoreHTTPSErrors: true,
    screenshot: 'on',
    trace: 'retain-on-failure',
    video: 'retain-on-failure',
  },
  projects,
});
