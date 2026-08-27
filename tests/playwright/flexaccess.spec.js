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

// Test data is chosen to be presentable: the screenshots and traces of a green run are used as
// illustrations for the handbook and the plugin description, so the forms should show plausible
// names rather than timestamps. Each CI run installs a fresh Moodle, so fixed values cannot clash.
// Addresses use example.org, which RFC 2606 reserves for documentation - no real mailbox can ever
// be hit by a test run.

const ADMIN_USER = process.env.FLEXACCESS_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.FLEXACCESS_ADMIN_PASS || 'Admin!23';
const COURSE_ID = process.env.FLEXACCESS_COURSE_ID;
const COURSE_NAME = process.env.FLEXACCESS_COURSE_NAME || 'My favourite course';

async function login(page) {
  await loginAs(page, ADMIN_USER, ADMIN_PASS);
  await expect(page).not.toHaveURL(/\/login\//);
}


/**
 * Fill a Moodle `passwordunmask` field.
 *
 * The element renders the real input behind an "click to enter text" anchor and keeps it hidden
 * until that anchor is used, so filling the input directly times out.
 *
 * @param {import('@playwright/test').Page} page The page under test.
 * @param {string} name The form field name.
 * @param {string} value The password to type.
 * @returns {Promise<void>}
 */
async function fillPasswordUnmask(page, name, value) {
  const input = page.locator(`input[name="${name}"]`);
  // The element hides the real input behind a "click to enter text" anchor until it is used.
  const edit = page.locator(
    `[data-passwordunmask="wrapper"]:has(input[name="${name}"]) [data-passwordunmask="edit"]`
  );
  if (await edit.count() && !(await input.isVisible())) {
    await edit.first().click();
  }
  await input.waitFor({ state: 'visible' });
  await fillStable(input, value);
}

/**
 * Build a readable address that stays unique across retries.
 *
 * A retry would otherwise register the very same address a second time and fail on the duplicate.
 * The first attempt - the one whose screenshots are used as illustrations - keeps the plain name.
 *
 * @param {string} local The local part, for example 'john.doe'.
 * @param {import('@playwright/test').TestInfo} testInfo The current test info.
 * @returns {string}
 */
function personEmail(local, testInfo) {
  return testInfo.retry ? `${local}.${testInfo.retry}@example.org` : `${local}@example.org`;
}

/**
 * Fill a field and make sure the value survives.
 *
 * Moodle's login password uses the `toggle_sensitive` component, whose JavaScript initialises after
 * the markup is in place and resets the field. Filling before that happens silently produced an
 * empty password: the value looked right, and the server then answered "Invalid login".
 *
 * @param {import('@playwright/test').Locator} field The input to fill.
 * @param {string} value The value to enter.
 * @returns {Promise<void>}
 */
async function fillStable(field, value) {
  let current = '';
  for (let attempt = 0; attempt < 3; attempt++) {
    await field.fill(value);
    // Give a late-initialising component the chance to reset the field before trusting the value.
    await field.page().waitForTimeout(300);
    current = await field.inputValue();
    if (current === value) {
      return;
    }
  }
  throw new Error(`The field kept losing its value; it now holds "${current}".`);
}

/**
 * Log in through Moodle's login form.
 *
 * Scoped to the login form and with the entered values verified: filling `#password` page-wide can
 * land on a different element, and the empty value only surfaces later as "Invalid login" on the
 * server. Checking the field before submitting turns that into an immediate, obvious failure.
 *
 * @param {import('@playwright/test').Page} page The page under test.
 * @param {string} username The username to use.
 * @param {string} password The password to use.
 * @returns {Promise<void>}
 */
async function loginAs(page, username, password) {
  await page.goto('/login/index.php');
  // Let the page's JavaScript settle first, otherwise a component that initialises afterwards can
  // still discard what was typed.
  await page.waitForLoadState('domcontentloaded');
  const form = page.locator('form[action*="login/index.php"]').first();
  const user = form.locator('input[name="username"]');
  const pass = form.locator('input[name="password"]');
  await fillStable(user, username);
  await fillStable(pass, password);
  await form.locator('#loginbtn, button[type="submit"], input[type="submit"]').first().click();
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
  const email = personEmail('john.doe', testInfo);
  const password = 'P@$$w0rd!';

  await context.clearCookies();
  await page.goto(`/auth/flexaccess/register.php?courseid=${COURSE_ID}`);
  await page.waitForLoadState('domcontentloaded');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="firstname"]', 'John');
  await page.fill('input[name="lastname"]', 'Doe');
  await fillPasswordUnmask(page, 'password', password);
  await page.getByRole('button', { name: /Create account and enter/i }).click();
  await expect(page.locator('body')).toContainText(COURSE_NAME);

  // Log out and log back in with the credentials just created.
  await context.clearCookies();
  await loginAs(page, email, password);
  await expect(page.locator('body')).toContainText('Quick Learner');
});

test('temporary access can be made permanent and log in again', async ({ page, context }) => {
  test.skip(!COURSE_ID, 'FLEXACCESS_COURSE_ID not provided by the seed step');
  const email = personEmail('jane.doe', testInfo);
  const password = 'P@$$w0rd!';

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
  await page.waitForLoadState('domcontentloaded');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="firstname"]', 'Jane');
  await page.fill('input[name="lastname"]', 'Doe');
  await fillPasswordUnmask(page, 'password', password);
  await page.getByRole('button', { name: /Make my account permanent/i }).click();
  await expect(page.locator('body')).toContainText(/permanent/i);

  // Log out and log back in.
  await context.clearCookies();
  await loginAs(page, email, password);
  await expect(page.locator('body')).toContainText('Persist Learner');
});
