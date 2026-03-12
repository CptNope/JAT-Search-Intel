import { test as setup, expect } from '@playwright/test';
import path from 'path';

const authFile = path.join(__dirname, '../../.auth/user.json');

/**
 * Authenticate as WP admin before running tests.
 * Stores session state for reuse across test files.
 */
setup('authenticate', async ({ page }) => {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', process.env.WP_ADMIN_USER || 'admin');
  await page.fill('#user_pass', process.env.WP_ADMIN_PASS || 'password');
  await page.click('#wp-submit');
  await expect(page).toHaveURL(/wp-admin/);
  await page.context().storageState({ path: authFile });
});
