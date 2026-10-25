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
 * FlexAccess across two Moodle sites connected by LTI 1.3 (enrol_flexaccess #10, auth_flexaccess #12/#13).
 *
 * Real, signed LTI 1.3 resource-link launches (OIDC login initiation, id_token, AGS, NRPS) between a
 * platform (consumer, mod_lti) and a provider (tool, enrol_lti + auth_lti), both with FlexAccess.
 * @provider journeys check what happens on the tool side (binding, merge, lifecycle), @consumer
 * journeys what the platform gets back (identity, grades in the gradebook), @browser journeys the
 * browser-specific behaviour (embedded launch, repeat launch, back navigation).
 */

const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');

const E = process.env;
const PLATFORM = E.LTI_PLATFORM_ISSUER;
const PROVIDER = new URL(E.LTI_PROVIDER_LAUNCH_URL || 'https://provider.test:8444/').origin;

/**
 * Run the server-side probe in one of the sites and return its JSON result.
 *
 * @param {'platform'|'provider'} site The site.
 * @param {...string|number} args Command and arguments.
 * @returns {any}
 */
function cli(site, ...args) {
  const root = site === 'platform' ? E.LTI_PLATFORM_ROOT : E.LTI_PROVIDER_ROOT;
  const env = { ...process.env };
  ['https_proxy', 'HTTPS_PROXY', 'http_proxy', 'HTTP_PROXY'].forEach((name) => delete env[name]);
  const out = execFileSync('php', [E.LTI_CLI, ...args.map(String)], { cwd: root, env, encoding: 'utf8' });
  return JSON.parse(out.trim().split('\n').pop());
}

/**
 * Navigate, retrying once or twice on a transport-level failure of the local test servers.
 *
 * PHP's built-in server occasionally drops a connection under the parallel module requests of Moodle
 * 5.x pages, which Chromium reports as net::ERR_TOO_MANY_RETRIES / ERR_EMPTY_RESPONSE. That is a
 * property of the test web server, not of Moodle or FlexAccess; any HTTP response is final.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string} url Absolute URL.
 * @param {object} [options] page.goto() options.
 * @returns {Promise<import('@playwright/test').Response|null>}
 */
async function open(page, url, options = {}) {
  for (let attempt = 1; ; attempt++) {
    try {
      return await page.goto(url, options);
    } catch (error) {
      if (attempt >= 3 || !/net::ERR_(TOO_MANY_RETRIES|EMPTY_RESPONSE|CONNECTION_RESET|CONNECTION_CLOSED)/.test(error.message)) {
        throw error;
      }
      await page.waitForTimeout(1000);
    }
  }
}

/**
 * Sign in on one of the sites through its login form.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string} base Site origin.
 * @param {string} username Username.
 * @param {string} password Password.
 * @returns {Promise<void>}
 */
async function login(page, base, username, password) {
  // The login page's JavaScript (password unmask) can replace the password field after load, which
  // empties a value filled too early: wait for it to settle and check the value before submitting.
  await open(page, `${base}/login/index.php`, { waitUntil: 'networkidle' });
  await submitLogin(page, username, password);
  await expect(page).not.toHaveURL(/\/login\/index\.php/);
}

/**
 * Fill and submit the login form on the current page.
 *
 * @param {import('@playwright/test').Page} page Page showing Moodle's login form.
 * @param {string} username Username.
 * @param {string} password Password.
 * @returns {Promise<void>}
 */
async function submitLogin(page, username, password) {
  await page.waitForLoadState('networkidle');
  await page.locator('#username').fill(username);
  await expect(async () => {
    await page.locator('#password').fill(password);
    await expect(page.locator('#password')).toHaveValue(password, { timeout: 1000 });
  }).toPass({ timeout: 10000 });
  await page.locator('#loginbtn').click();
  await page.waitForLoadState('domcontentloaded');
}

/**
 * Start the platform's external-tool activity as the signed-in learner (resource-link launch).
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string|number} cmid Course module id of the mod_lti activity.
 * @returns {Promise<void>}
 */
async function launch(page, cmid) {
  await open(page, `${PLATFORM}/mod/lti/launch.php?id=${cmid}&triggerview=0`);
  await page.waitForURL((url) => url.origin === PROVIDER, { timeout: 30000 });
  await page.waitForLoadState('domcontentloaded');
}

test.describe.serial('LTI 1.3 between two FlexAccess sites', () => {
  if (process.env.CI && !E.LTI_REGISTRATION_COMPLETE) {
    // In CI a missing setup is a failure, never a skip (no placebo green).
    throw new Error('The two LTI sites are not set up: setup-sites.sh did not complete.');
  }
  test.skip(!E.LTI_REGISTRATION_COMPLETE, 'The two LTI sites are not set up (run setup-sites.sh).');

  test('@provider first launch links the existing FlexAccess account; the relaunch needs no prompt', async ({ browser }) => {
    const before = cli('provider', 'countauth', 'lti');
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, PLATFORM, 'robin.platform', E.LTI_PLATFORM_PASS);
    await launch(page, E.LTI_PLATFORM_PROMPT_CMID);
    await expect(page).toHaveURL(/\/auth\/lti\/login\.php/);
    await expect(page.locator('body')).toContainText('I have an existing account');
    // Not signed in on the provider yet: "Log in", then "Link this account".
    await page.locator('input[name="existing_account"]').first().click();
    await expect(page).toHaveURL(/\/login\/index\.php/);
    await submitLogin(page, E.LTI_PROVIDER_DURABLE_USER, E.LTI_PROVIDER_DURABLE_PASS);
    await expect(page.locator('body')).toContainText('Robin Provider');
    await page.locator('input[name="existing_account"]').first().click();
    await expect(page.locator('body')).toContainText('Your existing account has been successfully linked.');

    // Bound to the original FlexAccess user id; no second local user was created.
    expect(cli('provider', 'binding', PLATFORM, E.LTI_PLATFORM_LEARNER_ID)).toBe(Number(E.LTI_PROVIDER_DURABLE_ID));
    expect(cli('provider', 'countauth', 'lti')).toBe(before);

    // The relaunch goes straight to the tool.
    await launch(page, E.LTI_PLATFORM_PROMPT_CMID);
    await expect(page).toHaveURL(/\/enrol\/lti\/launch\.php/);
    await expect(page.locator('body')).not.toContainText('I have an existing account');
    await context.close();
  });

  test('@provider the provider grade reaches the platform gradebook for the same learner (AGS)', async ({ browser }) => {
    expect(cli('provider', 'setgrade', E.LTI_PROVIDER_ASSIGN_ID, E.LTI_PROVIDER_DURABLE_ID, 85)).toBe('ok');
    cli('provider', 'task', '\\enrol_lti\\local\\ltiadvantage\\task\\sync_grades');
    expect(cli('platform', 'lineitemgrade', E.LTI_PLATFORM_PROMPT_LTIID, E.LTI_PLATFORM_LEARNER_ID)).toBe(85);

    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, PLATFORM, 'admin', 'Admin!23');
    await open(page, `${PLATFORM}/grade/report/grader/index.php?id=${E.LTI_PLATFORM_COURSE_ID}`);
    const row = page.locator('tr', { hasText: 'Robin Platform' }).first();
    await expect(row).toContainText('85.00');
    await context.close();
  });

  test('@provider a temporary FlexAccess session is never bound to the platform identity', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, PLATFORM, 'tara.platform', E.LTI_PLATFORM_PASS);
    await launch(page, E.LTI_PLATFORM_PROMPT_CMID);
    await expect(page.locator('body')).toContainText('I have an existing account');
    // The launch arrives as a cross-site POST, so it always starts a fresh provider session (Moodle's
    // session cookie is SameSite=Lax). Within that session, which holds the pending launch, the
    // visitor takes temporary FlexAccess access and returns to the account choice.
    await open(page, `${PROVIDER}/auth/flexaccess/access.php?courseid=${E.LTI_PROVIDER_COURSE_ID}`);
    await page.getByRole('button', { name: /Continue/i }).first().click();
    await expect(page).toHaveURL(/course\/view\.php/);
    await open(page, `${PROVIDER}/auth/lti/login.php`);
    await expect(page.locator('body')).toContainText('FlexAccess Guest');
    await page.locator('input[name="existing_account"]').first().click();
    await expect(page.locator('body')).toContainText('This FlexAccess access cannot be linked');
    expect(cli('provider', 'binding', PLATFORM, E.LTI_PLATFORM_TARA_ID)).toBe(0);
    // The pending launch was discarded: the same session cannot complete the binding afterwards.
    await open(page, `${PROVIDER}/auth/lti/login.php?existing_account=1`);
    expect(cli('provider', 'binding', PLATFORM, E.LTI_PLATFORM_TARA_ID)).toBe(0);
    await context.close();
  });

  test('@provider an account still waiting for its password cannot be used to link', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, PLATFORM, 'pat.platform', E.LTI_PLATFORM_PASS);
    await launch(page, E.LTI_PLATFORM_PROMPT_CMID);
    await page.locator('input[name="existing_account"]').first().click();
    await submitLogin(page, 'pat.provider', 'Provider-Pass!1');
    await expect(page).toHaveURL(/\/login\/index\.php/);
    await expect(page.locator('body')).toContainText(/Invalid login|Unable to log in/i);
    expect(cli('provider', 'binding', PLATFORM, E.LTI_PLATFORM_PAT_ID)).toBe(0);
    await context.close();
  });

  test('@provider FlexAccess expiry leaves LTI access; a suspended account cannot launch', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, PLATFORM, 'sam.platform', E.LTI_PLATFORM_PASS);
    await launch(page, E.LTI_PLATFORM_PROMPT_CMID);
    await page.locator('input[name="existing_account"]').first().click();
    await submitLogin(page, 'sam.provider', 'Provider-Pass!1');
    await page.locator('input[name="existing_account"]').first().click();
    await expect(page.locator('body')).toContainText('successfully linked');
    const sam = Number(E.LTI_PROVIDER_SAM_ID);
    expect(cli('provider', 'binding', PLATFORM, E.LTI_PLATFORM_SAM_ID)).toBe(sam);

    // The launch through the new binding enrols through the published resource (enrol_lti).
    await launch(page, E.LTI_PLATFORM_PROMPT_CMID);
    await expect(page).toHaveURL(/\/enrol\/lti\/launch\.php/);

    // Only the FlexAccess course access ends; the LTI enrolment of the launch still authorises.
    cli('provider', 'expireflexaccess', sam, E.LTI_PROVIDER_COURSE_ID);
    expect(cli('provider', 'ue', sam, E.LTI_PROVIDER_COURSE_ID, 'flexaccess').status).toBe(1);
    expect(cli('provider', 'ue', sam, E.LTI_PROVIDER_COURSE_ID, 'lti').status).toBe(0);
    await launch(page, E.LTI_PLATFORM_PROMPT_CMID);
    await expect(page).toHaveURL(/\/enrol\/lti\/launch\.php/);
    await expect(page.locator('body')).toContainText(/Open tool|Provider assignment/);

    // A suspended Moodle user gets no session through LTI.
    cli('provider', 'suspend', sam, 1);
    await launch(page, E.LTI_PLATFORM_PROMPT_CMID);
    await expect(page.locator('body')).not.toContainText(/Open tool/);
    await expect(page.locator('body')).toContainText(/Invalid login|Unable to log in|cannot be used/i);
    cli('provider', 'suspend', sam, 0);

    // A FlexAccess account that lapsed before the expiry task ran (not suspended yet) cannot launch either.
    cli('provider', 'accountstate', sam, 'expired');
    await launch(page, E.LTI_PLATFORM_PROMPT_CMID);
    await expect(page.locator('body')).not.toContainText(/Open tool/);
    await expect(page.locator('body')).toContainText(/Sign-in refused/);
    cli('provider', 'accountstate', sam, 'active');
    await context.close();
  });

  test('@provider automatic account, approved merge into the FlexAccess account, stable relaunch', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, PLATFORM, 'alex.platform', E.LTI_PLATFORM_PASS);
    await launch(page, E.LTI_PLATFORM_AUTO_CMID);
    await expect(page).toHaveURL(/\/enrol\/lti\/launch\.php/);
    // AUTO_ONLY: a distinct auth_lti user, never the FlexAccess account with the same e-mail address.
    const duplicate = cli('provider', 'binding', PLATFORM, E.LTI_PLATFORM_ALEX_ID);
    const alex = Number(E.LTI_PROVIDER_ALEX_ID);
    expect(duplicate).toBeGreaterThan(0);
    expect(duplicate).not.toBe(alex);
    expect(cli('provider', 'user', duplicate).user.auth).toBe('lti');

    // The administrator sees the probable duplicate and approves the merge after confirming ownership.
    const admin = await (await browser.newContext()).newPage();
    await login(admin, PROVIDER, 'admin', 'Admin!23');
    await open(admin, `${PROVIDER}/admin/tool/flexaccess/ltiidentities.php`);
    await expect(admin.locator('body')).toContainText('Probable duplicates');
    await admin.getByRole('link', { name: 'Review merge' }).first().click();
    // The approval names the independent ownership proof (auth_flexaccess #13); an e-mail address is none.
    await admin.getByLabel('Ownership proof').selectOption('reauthentication');
    await admin.getByLabel('Evidence').fill('Alex logged in with both accounts in front of the administrator.');
    await admin.getByRole('checkbox', { name: /belong to the same person/ }).check();
    await admin.getByRole('button', { name: 'Approve merge' }).click();
    await expect(admin.locator('body')).toContainText('Merge approved');

    // The merge itself (tool_mergeusers): keep the FlexAccess account, remove the LTI account.
    const merged = cli('provider', 'merge', alex, duplicate);
    expect(merged.success).toBe(true);
    expect(merged.registry.status).toBe('reconciled');
    expect(cli('provider', 'binding', PLATFORM, E.LTI_PLATFORM_ALEX_ID)).toBe(alex);

    // The next launch resolves to the surviving FlexAccess account.
    const users = cli('provider', 'countauth', 'lti');
    await launch(page, E.LTI_PLATFORM_AUTO_CMID);
    await expect(page).toHaveURL(/\/enrol\/lti\/launch\.php/);
    expect(cli('provider', 'binding', PLATFORM, E.LTI_PLATFORM_ALEX_ID)).toBe(alex);
    expect(cli('provider', 'countauth', 'lti')).toBe(users);

    // The FlexAccess password login still works after the merge.
    const own = await (await browser.newContext()).newPage();
    await login(own, PROVIDER, 'alex.provider', 'Provider-Pass!1');
    await expect(own).not.toHaveURL(/\/login\//);
    await context.close();
  });

  test('@consumer a FlexAccess learner launches with a stable identity and the grade is visible in the gradebook', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, PLATFORM, 'iris.platform', E.LTI_PLATFORM_PASS);
    await launch(page, E.LTI_PLATFORM_AUTO_CMID);
    const local = cli('provider', 'binding', PLATFORM, E.LTI_PLATFORM_IRIS_ID);
    expect(local).toBeGreaterThan(0);
    // Same platform identity (sub) on the relaunch: the same provider user.
    await launch(page, E.LTI_PLATFORM_AUTO_CMID);
    expect(cli('provider', 'binding', PLATFORM, E.LTI_PLATFORM_IRIS_ID)).toBe(local);

    expect(cli('provider', 'setgrade', E.LTI_PROVIDER_ASSIGN_ID, local, 72)).toBe('ok');
    cli('provider', 'task', '\\enrol_lti\\local\\ltiadvantage\\task\\sync_grades');
    // Raw grade data on the platform, for the canonical platform user ...
    expect(cli('platform', 'lineitemgrade', E.LTI_PLATFORM_AUTO_LTIID, E.LTI_PLATFORM_IRIS_ID)).toBe(72);
    // ... and visible to teachers: the FlexAccess participant role is a gradebook role (enrol #8).
    const admin = await (await browser.newContext()).newPage();
    await login(admin, PLATFORM, 'admin', 'Admin!23');
    await open(admin, `${PLATFORM}/grade/report/grader/index.php?id=${E.LTI_PLATFORM_COURSE_ID}`);
    await expect(admin.locator('tr', { hasText: 'Iris Platform' }).first()).toContainText('72.00');
    await context.close();
  });

  test('@consumer a temporary learner keeps identity and grade when the access becomes permanent', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await open(page, `${PLATFORM}/auth/flexaccess/access.php?courseid=${E.LTI_PLATFORM_COURSE_ID}`);
    await page.getByRole('button', { name: /Continue/i }).first().click();
    await expect(page).toHaveURL(/course\/view\.php/);
    const visitor = cli('platform', 'newestflexaccessuser');
    expect(cli('platform', 'user', visitor).accounttype).toBe('temporary user');

    await launch(page, E.LTI_PLATFORM_AUTO_CMID);
    const local = cli('provider', 'binding', PLATFORM, visitor);
    expect(local).toBeGreaterThan(0);
    expect(cli('provider', 'setgrade', E.LTI_PROVIDER_ASSIGN_ID, local, 64)).toBe('ok');
    cli('provider', 'task', '\\enrol_lti\\local\\ltiadvantage\\task\\sync_grades');
    expect(cli('platform', 'lineitemgrade', E.LTI_PLATFORM_AUTO_LTIID, visitor)).toBe(64);

    // Permanent now: same platform user id, so the platform identity (sub) does not change.
    const persisted = cli('platform', 'persist', visitor, 'vera@example.org', 'Vera', 'Visitor', 'Vera-Pass!123');
    expect(persisted.status).toBe('converted');
    expect(cli('platform', 'user', visitor).accounttype).toBe('authenticated user');
    const own = await (await browser.newContext()).newPage();
    await login(own, PLATFORM, persisted.username, 'Vera-Pass!123');
    await launch(own, E.LTI_PLATFORM_AUTO_CMID);
    expect(cli('provider', 'binding', PLATFORM, visitor)).toBe(local);
    expect(cli('platform', 'lineitemgrade', E.LTI_PLATFORM_AUTO_LTIID, visitor)).toBe(64);
    await context.close();
  });

  test('@consumer @browser embedded launch, repeat launch and back navigation', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, PLATFORM, 'iris.platform', E.LTI_PLATFORM_PASS);
    await open(page, `${PLATFORM}/mod/lti/view.php?id=${E.LTI_PLATFORM_EMBED_CMID}`);
    const frame = page.frameLocator('iframe#contentframe');
    await expect(frame.locator('body')).toContainText(/Provider assignment|Open tool/, { timeout: 30000 });
    // Repeat launch in the same browser session.
    await page.reload();
    await expect(frame.locator('body')).toContainText(/Provider assignment|Open tool/, { timeout: 30000 });
    // Back navigation returns to the platform course without losing the platform session.
    await open(page, `${PLATFORM}/course/view.php?id=${E.LTI_PLATFORM_COURSE_ID}`);
    await page.goBack();
    await expect(page).toHaveURL(/\/mod\/lti\/view\.php/);
    await expect(page.locator('body')).toContainText('Iris Platform');
    await context.close();
  });

  test('@provider NRPS membership sync after the merge neither recreates nor unbinds identities', async ({ browser }) => {
    const alex = Number(E.LTI_PROVIDER_ALEX_ID);
    cli('provider', 'task', '\\enrol_lti\\local\\ltiadvantage\\task\\sync_members');
    expect(cli('provider', 'binding', PLATFORM, E.LTI_PLATFORM_ALEX_ID)).toBe(alex);
    expect(cli('provider', 'binding', PLATFORM, E.LTI_PLATFORM_LEARNER_ID)).toBe(Number(E.LTI_PROVIDER_DURABLE_ID));
    // The FlexAccess account of the merged person keeps its own enrolment.
    expect(cli('provider', 'ue', alex, E.LTI_PROVIDER_COURSE_ID, 'flexaccess').status).toBe(0);
    // AUTO_ONLY pre-provisioning: a learner who never launched now has an auth_lti account ...
    const pre = cli('provider', 'binding', PLATFORM, E.LTI_PLATFORM_PAT_ID);
    expect(pre).toBeGreaterThan(0);
    expect(pre).not.toBe(Number(E.LTI_PROVIDER_PAT_ID));

    // Grade delivery after the merge goes to the same platform learner (AGS through the re-pointed membership).
    expect(cli('provider', 'setgrade', E.LTI_PROVIDER_ASSIGN_ID, alex, 91)).toBe('ok');
    cli('provider', 'task', '\\enrol_lti\\local\\ltiadvantage\\task\\sync_grades');
    expect(cli('platform', 'lineitemgrade', E.LTI_PLATFORM_AUTO_LTIID, E.LTI_PLATFORM_ALEX_ID)).toBe(91);

    // Switching the resource from AUTO_ONLY to "prompt for existing account" neither prompts nor rebinds
    // an identity that is already bound.
    cli('provider', 'provisioning', E.LTI_PROVIDER_AUTO_TOOLID, 3);
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, PLATFORM, 'alex.platform', E.LTI_PLATFORM_PASS);
    await launch(page, E.LTI_PLATFORM_AUTO_CMID);
    await expect(page).toHaveURL(/\/enrol\/lti\/launch\.php/);
    await expect(page.locator('body')).not.toContainText('I have an existing account');
    expect(cli('provider', 'binding', PLATFORM, E.LTI_PLATFORM_ALEX_ID)).toBe(alex);
    cli('provider', 'provisioning', E.LTI_PROVIDER_AUTO_TOOLID, 1);
    await context.close();
  });
});
