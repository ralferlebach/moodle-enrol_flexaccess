// Browser smoke test for enrol_flexaccess.
// Verifies that an administrator can log in and that the FlexAccess enrolment method
// is present on the "Manage enrol plugins" page. This proves the plugin installed and
// renders inside a real Moodle, without depending on the (still evolving) anonymous flow.
const { test, expect } = require('@playwright/test');

const ADMIN_USER = process.env.FLEXACCESS_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.FLEXACCESS_ADMIN_PASS || 'Admin!23';

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
