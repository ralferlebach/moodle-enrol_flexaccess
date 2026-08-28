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
 * What enrol_flexaccess is responsible for, exercised in a browser: the enrolment method itself,
 * the access policy it applies to a course, and its per-course restriction administration.
 */

const { test, expect } = require('@playwright/test');
const { loginAs, fillPasswordUnmask, open, submitForm, chooseCourse } = require('./helpers');

const ADMIN_USER = process.env.FLEXACCESS_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.FLEXACCESS_ADMIN_PASS || 'Admin!23';
const COURSE_ID = process.env.FLEXACCESS_COURSE_ID;
const COURSE_NAME = process.env.FLEXACCESS_COURSE_NAME || 'My favourite course';

/**
 * Build a readable address that stays unique across retries.
 *
 * A retry would otherwise reuse an address that the first attempt already registered. The first
 * attempt - the one whose screenshots are used as illustrations - keeps the plain name.
 *
 * @param {string} local The local part, for example 'john.doe'.
 * @param {import('@playwright/test').TestInfo} testInfo The current test info.
 * @returns {string}
 */
function personEmail(local, testInfo) {
  return testInfo.retry ? `${local}.${testInfo.retry}@example.org` : `${local}@example.org`;
}

test('the enrolment method is installed and listed', async ({ page }) => {
  await loginAs(page, ADMIN_USER, ADMIN_PASS);
  await open(page, '/admin/settings.php?section=manageenrols');
  await expect(page.locator('body')).toContainText('FlexAccess enrolment');
});

test('the course policy reaches the entry page', async ({ page, context }) => {
  test.skip(!COURSE_ID, 'FLEXACCESS_COURSE_ID not provided by the seed step');
  // The fixture enables temporary access and quick registration, so both have to be on offer.
  await context.clearCookies();
  await page.goto(`/auth/flexaccess/access.php?courseid=${COURSE_ID}`);
  await expect(page.locator('body')).toContainText(COURSE_NAME);
  await expect(page.getByRole('button', { name: /Continue/i }).first()).toBeVisible();
});

test('the enrolment instance can be configured in the course', async ({ page }) => {
  test.skip(!COURSE_ID, 'FLEXACCESS_COURSE_ID not provided by the seed step');
  await loginAs(page, ADMIN_USER, ADMIN_PASS);
  await open(page, `/enrol/instances.php?id=${COURSE_ID}`);
  await expect(page.locator('body')).toContainText('FlexAccess');
});

test('role and cohort restrictions can be administered per course', async ({ page }) => {
  test.skip(!COURSE_ID, 'FLEXACCESS_COURSE_ID not provided by the seed step');
  await loginAs(page, ADMIN_USER, ADMIN_PASS);
  await open(page, `/enrol/flexaccess/restrictions.php?courseid=${COURSE_ID}`);
  await expect(page.locator('body')).toContainText(/restriction|beschr/i);
});
