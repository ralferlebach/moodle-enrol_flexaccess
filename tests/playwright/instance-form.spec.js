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
 * Edit-form round trip of the FlexAccess enrolment method (issues #12 and #13).
 *
 * Runs on a course of its own (FLEXACCESS_FORM_COURSE_ID from seed.php). After every save the stored
 * row and the effective course policy are read on the server with probe.php, so the journey checks
 * the database, not only what the browser shows.
 */

const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');
const { loginAs, open, submitForm } = require('./helpers');

const ADMIN_USER = process.env.FLEXACCESS_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.FLEXACCESS_ADMIN_PASS || 'Admin!23';
const COURSE_ID = process.env.FLEXACCESS_FORM_COURSE_ID;
const ENROL_ID = process.env.FLEXACCESS_FORM_ENROL_ID;
const COURSE_NAME = 'Form round trip course';

/**
 * Read the stored instance state on the server.
 *
 * @returns {{row: object, coreenrolperiod: number, local: object, policy: object}}
 */
function probe() {
  const out = execFileSync('php', ['probe.php', 'instance', String(ENROL_ID)], { cwd: __dirname, encoding: 'utf8' });
  return JSON.parse(out.trim().split('\n').pop());
}

/**
 * Open the edit form with all sections expanded.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @returns {Promise<void>}
 */
async function openForm(page) {
  await open(page, `/enrol/editinstance.php?courseid=${COURSE_ID}&id=${ENROL_ID}&type=flexaccess`);
  const expand = page.locator('.collapseexpand').first();
  if (await expand.count() && !/collapse-all|Collapse all/i.test((await expand.getAttribute('class')) + await expand.innerText())) {
    await expand.click();
  }
}

/**
 * Enable an optional date-time selector and choose a time (in the user's time zone).
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string} name Element name.
 * @param {object} time Parts: year, month, day, hour, minute.
 * @returns {Promise<void>}
 */
async function setDate(page, name, time) {
  await page.locator(`input[name="${name}[enabled]"]`).check({ force: true });
  for (const part of ['year', 'month', 'day', 'hour', 'minute']) {
    await page.locator(`select[name="${name}[${part}]"]`).selectOption(String(time[part]), { force: true });
  }
}

/**
 * Save the form and wait for the list of enrolment methods.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @returns {Promise<void>}
 */
async function save(page) {
  await submitForm(page);
  await expect(page).toHaveURL(/enrol\/instances\.php/);
}

/**
 * Whether an anonymous visitor is offered an entry to the course.
 *
 * The entry page names the course only when it offers an anonymous entry; otherwise it shows a
 * generic notice (enumeration guard) whose only button leads to the login page.
 *
 * @param {import('@playwright/test').Browser} browser The browser.
 * @returns {Promise<boolean>}
 */
async function visitorOffered(browser) {
  const context = await browser.newContext();
  const visitor = await context.newPage();
  await visitor.goto(`/auth/flexaccess/access.php?courseid=${COURSE_ID}`);
  await visitor.waitForLoadState('domcontentloaded');
  const offered = await visitor.getByText(COURSE_NAME).count();
  await context.close();
  return offered > 0;
}

test('the instance form keeps window and capacity across save and reopen', async ({ page, browser }) => {
  test.skip(!COURSE_ID || !ENROL_ID, 'FLEXACCESS_FORM_COURSE_ID not provided by the seed step');
  const seeded = probe();
  expect(await visitorOffered(browser)).toBe(true);
  await loginAs(page, ADMIN_USER, ADMIN_PASS);

  // 1. A window in the future and a capacity of 17.
  await openForm(page);
  await setDate(page, 'availablefrom', { year: 2030, month: 1, day: 1, hour: 10, minute: 0 });
  await setDate(page, 'availableuntil', { year: 2030, month: 2, day: 1, hour: 18, minute: 30 });
  await page.locator('input[name="maxparticipants"]').fill('17');
  await save(page);

  const saved = probe();
  expect(saved.local).toEqual({ availablefrom: '2030-01-01 10:00', availableuntil: '2030-02-01 18:30' });
  expect(Number(saved.row.maxparticipants)).toBe(17);
  // Fields the journey did not touch keep their seeded values, in both rows.
  expect(saved.row.allowtemporary).toBe(seeded.row.allowtemporary);
  expect(saved.row.enrolperiod).toBe(seeded.row.enrolperiod);
  expect(saved.coreenrolperiod).toBe(Number(seeded.row.enrolperiod));
  // The effective course policy follows at once: the window has not opened yet.
  expect(saved.policy).toMatchObject({
    availablefrom: Number(saved.row.availablefrom),
    availableuntil: Number(saved.row.availableuntil),
    maxparticipants: 17,
    windowopen: false,
  });
  expect(await visitorOffered(browser)).toBe(false);

  // 2. Reopen: the stored values are shown; saving unchanged writes nothing.
  await openForm(page);
  await expect(page.locator('input[name="availablefrom[enabled]"]')).toBeChecked();
  await expect(page.locator('select[name="availablefrom[year]"]')).toHaveValue('2030');
  await expect(page.locator('select[name="availablefrom[hour]"]')).toHaveValue('10');
  await expect(page.locator('input[name="availableuntil[enabled]"]')).toBeChecked();
  await expect(page.locator('select[name="availableuntil[month]"]')).toHaveValue('2');
  await expect(page.locator('select[name="availableuntil[minute]"]')).toHaveValue('30');
  await expect(page.locator('input[name="maxparticipants"]')).toHaveValue('17');
  await save(page);
  expect(probe().row).toEqual(saved.row);

  // 3. Clearing only the start and the capacity keeps the end; the course is open again.
  await openForm(page);
  await page.locator('input[name="availablefrom[enabled]"]').uncheck({ force: true });
  await page.locator('input[name="maxparticipants"]').fill('0');
  await save(page);
  const cleared = probe();
  expect(Number(cleared.row.availablefrom)).toBe(0);
  expect(cleared.row.availableuntil).toBe(saved.row.availableuntil);
  expect(Number(cleared.row.maxparticipants)).toBe(0);
  expect(cleared.policy).toMatchObject({ availablefrom: 0, maxparticipants: 0, windowopen: true });
  expect(await visitorOffered(browser)).toBe(true);
});

/**
 * Set the administrator's time zone on the server and sign in again (the zone is read at login).
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string} zone IANA time zone.
 * @returns {Promise<void>}
 */
async function loginInZone(page, zone) {
  execFileSync('php', ['probe.php', 'timezone', zone], { cwd: __dirname, encoding: 'utf8' });
  await page.context().clearCookies();
  await loginAs(page, ADMIN_USER, ADMIN_PASS);
}

// Regression of the reported bug (enrol_flexaccess #12): 21.-22. October, 00:00-23:59 local, in summer time.
// The UTC instants are what the user meant in each zone; the window lies in the past, so the entry is closed.
const OCTOBER = [
  { zone: 'Europe/Berlin', from: Date.UTC(2026, 9, 20, 22, 0) / 1000, until: Date.UTC(2026, 9, 22, 21, 59) / 1000 },
  { zone: 'America/Los_Angeles', from: Date.UTC(2026, 9, 21, 7, 0) / 1000, until: Date.UTC(2026, 9, 23, 6, 59) / 1000 },
];

for (const { zone, from, until } of OCTOBER) {
  test(`21.-22. October in ${zone}: stored as local days, shown again, entry closed afterwards`, async ({ page, browser }) => {
    test.skip(!COURSE_ID || !ENROL_ID, 'FLEXACCESS_FORM_COURSE_ID not provided by the seed step');
    await loginInZone(page, zone);
    await openForm(page);
    await setDate(page, 'availablefrom', { year: 2026, month: 10, day: 21, hour: 0, minute: 0 });
    await setDate(page, 'availableuntil', { year: 2026, month: 10, day: 22, hour: 23, minute: 59 });
    await page.locator('input[name="maxparticipants"]').fill('17');
    await save(page);
    const saved = probe();
    expect(saved.local).toEqual({ availablefrom: '2026-10-21 00:00', availableuntil: '2026-10-22 23:59' });
    expect([Number(saved.row.availablefrom), Number(saved.row.availableuntil)]).toEqual([from, until]);
    expect(Number(saved.row.maxparticipants)).toBe(17);
    expect(saved.policy.windowopen).toBe(false);
    expect(await visitorOffered(browser)).toBe(false);

    await openForm(page);
    await expect(page.locator('select[name="availablefrom[day]"]')).toHaveValue('21');
    await expect(page.locator('select[name="availablefrom[hour]"]')).toHaveValue('0');
    await expect(page.locator('select[name="availableuntil[day]"]')).toHaveValue('22');
    await expect(page.locator('select[name="availableuntil[hour]"]')).toHaveValue('23');
    await expect(page.locator('select[name="availableuntil[minute]"]')).toHaveValue('59');
    await save(page);
    expect(probe().row).toEqual(saved.row);
  });
}

test('a window across the end of summer time keeps its local wall-clock times', async ({ page }) => {
  test.skip(!COURSE_ID || !ENROL_ID, 'FLEXACCESS_FORM_COURSE_ID not provided by the seed step');
  const cases = [
    // Berlin leaves CEST on 2026-10-25, Los Angeles leaves PDT on 2026-11-01: 49 real hours in both.
    { zone: 'Europe/Berlin', start: { year: 2026, month: 10, day: 24 }, end: { year: 2026, month: 10, day: 26 } },
    { zone: 'America/Los_Angeles', start: { year: 2026, month: 10, day: 31 }, end: { year: 2026, month: 11, day: 2 } },
  ];
  for (const { zone, start, end } of cases) {
    await loginInZone(page, zone);
    await openForm(page);
    await setDate(page, 'availablefrom', { ...start, hour: 12, minute: 0 });
    await setDate(page, 'availableuntil', { ...end, hour: 12, minute: 0 });
    await save(page);
    const saved = probe();
    const pad = (n) => String(n).padStart(2, '0');
    expect(saved.local).toEqual({
      availablefrom: `${start.year}-${pad(start.month)}-${pad(start.day)} 12:00`,
      availableuntil: `${end.year}-${pad(end.month)}-${pad(end.day)} 12:00`,
    });
    expect(Number(saved.row.availableuntil) - Number(saved.row.availablefrom)).toBe(49 * 3600);
  }
});

test('an end before the start is refused and changes nothing', async ({ page }) => {
  test.skip(!COURSE_ID || !ENROL_ID, 'FLEXACCESS_FORM_COURSE_ID not provided by the seed step');
  await loginInZone(page, 'Europe/Berlin');
  const before = probe().row;
  await openForm(page);
  await setDate(page, 'availablefrom', { year: 2030, month: 10, day: 22, hour: 0, minute: 0 });
  await setDate(page, 'availableuntil', { year: 2030, month: 10, day: 21, hour: 23, minute: 59 });
  await submitForm(page);
  await expect(page).toHaveURL(/enrol\/editinstance\.php/);
  await expect(page.locator('#region-main')).toContainText('must be after the "available from" time');
  expect(probe().row).toEqual(before);
});
