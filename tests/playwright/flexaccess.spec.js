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
 * Browser tests for the FlexAccess ecosystem.
 *
 * @module     enrol_flexaccess/flexaccess.spec
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { test, expect } = require('@playwright/test');

const ADMIN_USER = process.env.FLEXACCESS_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.FLEXACCESS_ADMIN_PASS || 'Admin!23';
const COURSE_ID = process.env.FLEXACCESS_COURSE_ID;
const COURSE_NAME = process.env.FLEXACCESS_COURSE_NAME || 'FlexAccess Load Test';

async function login(page) {
  await page.goto('/login/index.php');
  await page.fill('#username', ADMIN_USER);
  await page.fill('#password', ADMIN_PASS);
  await page.click('#loginbtn');
  await expect(page).not.toHaveURL(/\/login\//);
}

test('FlexAccess enrolment method is installed and listed', async ({ page }) => {
  await login(page);
  await page.goto('/admin/settings.php?section=manageenrols');
  await expect(page.locator('body')).toContainText('FlexAccess enrolment');
});

test('anonymous visitor gains temporary access through the entry page', async ({ page, context }) => {
  test.skip(!COURSE_ID, 'FLEXACCESS_COURSE_ID not provided by the fixture step');

  // Start from a clean, unauthenticated session.
  await context.clearCookies();
  await page.goto(`/auth/flexaccess/access.php?courseid=${COURSE_ID}`);

  // The entry page names the target course and offers a way in.
  await expect(page.locator('body')).toContainText(COURSE_NAME);

  // "Continue" grants temporary access; it may render as a button or a link.
  const button = page.getByRole('button', { name: /Continue/i });
  if (await button.count()) {
    await button.first().click();
  } else {
    await page.getByRole('link', { name: /Continue/i }).first().click();
  }

  // The visitor lands inside the course as a temporary user.
  await expect(page).toHaveURL(/course\/view\.php/);
  await expect(page.locator('body')).toContainText(COURSE_NAME);
});

test('magic-login request page renders for anonymous users', async ({ page, context }) => {
  await context.clearCookies();
  await page.goto('/auth/flexaccess/magic.php');
  await expect(page.locator('body')).toContainText('email link');
  await expect(page.locator('input[name="email"]')).toBeVisible();
});
