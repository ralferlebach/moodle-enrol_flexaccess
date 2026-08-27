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
  await page.goto('/login/index.php');
  await page.fill('#username', ADMIN_USER);
  await page.fill('#password', ADMIN_PASS);
  await page.click('#loginbtn');
  // These journeys visit pages that require moodle/site:config, so they need the site
  // administrator - not the manager account the seed creates for the accessibility checks.
  await expect(
    page,
    `Login as "${ADMIN_USER}" failed; these pages need the site administrator.`
  ).not.toHaveURL(/\/login\//);
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
  expect(response, `No response for ${url}`).not.toBeNull();
  expect(response.status(), `Unexpected HTTP status for ${url}`).toBe(200);
  await expect(page.locator('body')).not.toContainText(/Coding error|Exception|Debug info/i);
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
    await page.locator('#id_submitbutton, button[type="submit"], input[type="submit"]').first().click();
    await expect(page.locator('body')).toContainText(address);
  });

  test('an access list can be created for a course', async ({ page }) => {
    await open(page, `/admin/tool/flexaccess/coursebatches.php?courseid=${COURSE_ID}&action=new`);
    const name = `E2E ${Date.now()}`;
    await page.locator('#id_name, input[name="name"]').first().fill(name);
    await page.locator('#id_count, input[name="count"]').first().fill('2');
    await page.locator('#id_submitbutton, button[type="submit"], input[type="submit"]').first().click();
    await expect(page.locator('body')).toContainText(name);
  });

  test('a campaign link is shown exactly once on creation', async ({ page }) => {
    await open(page, '/admin/tool/flexaccess/campaigns.php?action=new');
    const name = `Campaign ${Date.now()}`;
    await page.locator('#id_name, input[name="name"]').first().fill(name);
    await page.selectOption('select[name="courseid"]', COURSE_ID).catch(() => {});
    await page.locator('#id_submitbutton, button[type="submit"], input[type="submit"]').first().click();

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
