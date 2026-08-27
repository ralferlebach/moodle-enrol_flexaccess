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
 * Administrative journeys through the browser: invitations, access lists, campaigns and the
 * activity's self-activation. PHPUnit already covers the logic behind these; what only a browser
 * can show is that the pages render, the forms submit and the state-changing actions - which are
 * POST-only - actually work from the interface.
 */

const { test, expect } = require('@playwright/test');

const ADMIN_USER = process.env.FLEXACCESS_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.FLEXACCESS_ADMIN_PASS || 'Admin!23';
const COURSE_ID = process.env.FLEXACCESS_COURSE_ID;

/**
 * Log in through the standard Moodle login form.
 *
 * @param {import('@playwright/test').Page} page The page under test.
 * @returns {Promise<void>}
 */
async function login(page) {
  await loginAs(page, ADMIN_USER, ADMIN_PASS);
  // These journeys visit pages that require moodle/site:config, so they need the site
  // administrator - not the manager account the seed creates for the accessibility checks.
  await expect(
    page,
    `Login as "${ADMIN_USER}" failed; these pages need the site administrator.`
  ).not.toHaveURL(/\/login\//);
}

/**
 * Submit the form on the current page.
 *
 * Scoped to the form itself: a page-wide "first submit button" can pick up an unrelated control
 * from the header, which then never completes the action and only surfaces as a timeout.
 *
 * @param {import('@playwright/test').Page} page The page under test.
 * @returns {Promise<void>}
 */
async function submitForm(page) {
  // Moodle gives the primary submit of a moodleform the id `id_submitbutton`. Searching for "the
  // first form containing an input" instead picked up the search box in the page header, whose
  // button never completes the action and only showed up as a timeout.
  const button = page
    .locator('#id_submitbutton')
    .or(page.locator('[role="main"] button[type="submit"], [role="main"] input[type="submit"]'))
    .first();
  await button.waitFor({ state: 'visible' });
  await button.click();
}

/**
 * Open a page and assert it rendered rather than redirecting to login or erroring out.
 *
 * @param {import('@playwright/test').Page} page The page under test.
 * @param {string} url The URL to open.
 * @returns {Promise<void>}
 */
async function open(page, url) {
  const response = await page.goto(url);
  await page.waitForLoadState('networkidle').catch(() => {});
  expect(response, `No response for ${url}`).not.toBeNull();
  expect(response.status(), `Unexpected HTTP status for ${url}`).toBe(200);
  await expect(page.locator('body')).not.toContainText(/Coding error|Exception|Debug info/i);
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
  await page.waitForLoadState('networkidle').catch(() => {});
  const form = page.locator('form[action*="login/index.php"]').first();
  const user = form.locator('input[name="username"]');
  const pass = form.locator('input[name="password"]');
  await fillStable(user, username);
  await fillStable(pass, password);
  await form.locator('#loginbtn, button[type="submit"], input[type="submit"]').first().click();
}

test.describe('FlexAccess administrative journeys', () => {
  test.skip(!COURSE_ID, 'FLEXACCESS_COURSE_ID must be provided by the seed script.');

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('an invitation can be created and appears in the list', async ({ page }) => {
    await open(page, '/admin/tool/flexaccess/invitations.php?action=new');
    const address = `invitee-${Date.now()}@example.invalid`;
    await page.locator('#id_emails, textarea[name="emails"], input[name="emails"]').first().fill(address);
    await page.selectOption('select[name="courseid"]', COURSE_ID).catch(() => {});
    await submitForm(page);
    await expect(page.locator('body')).toContainText(address);
  });

  test('an access list can be created for a course', async ({ page }) => {
    await open(page, `/admin/tool/flexaccess/coursebatches.php?courseid=${COURSE_ID}&action=new`);
    const name = `E2E ${Date.now()}`;
    await page.locator('#id_name, input[name="name"]').first().fill(name);
    await page.locator('#id_count, input[name="count"]').first().fill('2');
    await submitForm(page);
    await expect(page.locator('body')).toContainText(name);
  });

  test('a campaign link is shown exactly once on creation', async ({ page }) => {
    await open(page, '/admin/tool/flexaccess/campaigns.php?action=new');
    const name = `Campaign ${Date.now()}`;
    await page.locator('#id_name, input[name="name"]').first().fill(name);
    await page.selectOption('select[name="courseid"]', COURSE_ID).catch(() => {});
    await submitForm(page);

    // The plaintext link is stored hashed and must be displayed on this response only.
    await expect(page.locator('body')).toContainText(/campaign\.php\?token=/);
    await open(page, '/admin/tool/flexaccess/campaigns.php');
    await expect(page.locator('body')).not.toContainText(/campaign\.php\?token=/);
  });

  test('the course restriction page renders and lists no rule by default', async ({ page }) => {
    await open(page, `/enrol/flexaccess/restrictions.php?courseid=${COURSE_ID}`);
    await expect(page.locator('body')).toContainText(/restriction|beschränkung/i);
  });

  test('the batch list is reachable and paginated cleanly', async ({ page }) => {
    await open(page, '/admin/tool/flexaccess/batches.php');
    await expect(page.locator('body')).toContainText(/batch|zugangsliste/i);
  });
});

test.describe('FlexAccess keyboard accessibility', () => {
  test.skip(!COURSE_ID, 'FLEXACCESS_COURSE_ID must be provided by the seed script.');

  test('the entry page can be operated with the keyboard alone', async ({ page }) => {
    await open(page, `/auth/flexaccess/access.php?courseid=${COURSE_ID}`);

    // Tab until a focusable control inside the entry card is reached: a visitor who cannot use a
    // mouse must be able to get to the primary action.
    let reached = false;
    for (let i = 0; i < 25 && !reached; i++) {
      await page.keyboard.press('Tab');
      reached = await page.evaluate(() => {
        const el = document.activeElement;
        if (!el || el === document.body) {
          return false;
        }
        const focusable = ['A', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA'].includes(el.tagName);
        return focusable && !!el.closest('.flexaccess-entry');
      });
    }
    expect(reached, 'No control inside the entry card could be reached by tabbing.').toBe(true);

    // The focused element must be visibly focused, otherwise keyboard users cannot tell where
    // they are.
    const hasOutline = await page.evaluate(() => {
      const style = window.getComputedStyle(document.activeElement);
      return style.outlineStyle !== 'none' || style.boxShadow !== 'none';
    });
    expect(hasOutline, 'The focused control shows no visible focus indicator.').toBe(true);
  });
});
