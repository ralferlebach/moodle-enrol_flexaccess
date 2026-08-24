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

test('quick registration creates a persistent account that can log in again', async ({ page, context }) => {
  test.skip(!COURSE_ID, 'FLEXACCESS_COURSE_ID not provided by the seed step');
  const email = `pw_quick_${Date.now()}@example.com`;
  const password = 'Str0ng-Pass!23';

  await context.clearCookies();
  await page.goto(`/auth/flexaccess/register.php?courseid=${COURSE_ID}`);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="firstname"]', 'Quick');
  await page.fill('input[name="lastname"]', 'Learner');
  await page.fill('input[name="password"]', password);
  await page.getByRole('button', { name: /Create account and enter/i }).click();
  await expect(page.locator('body')).toContainText(COURSE_NAME);

  // Log out and log back in with the credentials just created.
  await context.clearCookies();
  await page.goto('/login/index.php');
  await page.fill('#username', email);
  await page.fill('#password', password);
  await page.click('#loginbtn');
  await expect(page.locator('body')).toContainText('Quick Learner');
});

test('temporary access can be made permanent and log in again', async ({ page, context }) => {
  test.skip(!COURSE_ID, 'FLEXACCESS_COURSE_ID not provided by the seed step');
  const email = `pw_persist_${Date.now()}@example.com`;
  const password = 'Str0ng-Pass!23';

  // Enter anonymously as a temporary user.
  await context.clearCookies();
  await page.goto(`/auth/flexaccess/access.php?courseid=${COURSE_ID}`);
  const cont = page.getByRole('button', { name: /Continue/i });
  if (await cont.count()) {
    await cont.first().click();
  } else {
    await page.getByRole('link', { name: /Continue/i }).first().click();
  }
  await expect(page).toHaveURL(/course\/view\.php/);

  // Make the account permanent (verification is disabled on the test site).
  await page.goto('/auth/flexaccess/persist.php');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="firstname"]', 'Persist');
  await page.fill('input[name="lastname"]', 'Learner');
  await page.fill('input[name="password"]', password);
  await page.getByRole('button', { name: /Make my account permanent/i }).click();
  await expect(page.locator('body')).toContainText(/permanent/i);

  // Log out and log back in.
  await context.clearCookies();
  await page.goto('/login/index.php');
  await page.fill('#username', email);
  await page.fill('#password', password);
  await page.click('#loginbtn');
  await expect(page.locator('body')).toContainText('Persist Learner');
});
