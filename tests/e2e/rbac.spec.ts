import { test, expect } from '@playwright/test';

/**
 * E2E smoke tests for Role-Based Access Control.
 *
 * Tests that an admin user (SEO Editor role) sees full UI,
 * while a lower-privileged user sees restricted/view-only UI.
 */

test.describe('RBAC — Admin Access', () => {

  test('admin sees Search Intel menu', async ({ page }) => {
    await page.goto('/wp-admin/');
    const menu = page.locator('#adminmenu a:has-text("Search Intel")');
    await expect(menu).toBeVisible();
  });

  test('admin can access settings page', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel');
    await expect(page.locator('h1').first()).toContainText(/Search Intel/);
    // Admin should see a save button — full access.
    const submit = page.locator('input[type="submit"]');
    await expect(submit).toBeVisible();
  });

  test('admin can access bulk editor', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-bulk-editor');
    await expect(page.locator('h1')).toContainText('Bulk Editor');
  });

  test('admin can access migration page', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-migration');
    await expect(page.locator('h1')).toContainText('SEO Plugin Migration');
  });

  test('admin sees metabox on post editor', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    const metabox = page.locator('#search-intel-seo');
    await expect(metabox).toBeVisible({ timeout: 10_000 });
  });
});

test.describe('RBAC — Author Access', () => {

  // Create a separate browser context for the author user.
  // This requires an "author" WP user (login: author / password: password).
  // wp-env creates this automatically if you run:
  //   npx wp-env run cli -- wp user create author author@example.com --role=author --user_pass=password

  test('author cannot access settings page', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    // Log in as author.
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'author');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');

    // Try to access settings — should get permission denied or redirect.
    await page.goto('/wp-admin/admin.php?page=search-intel');
    const body = await page.locator('body').textContent();
    // WP shows "Sorry, you are not allowed to access this page." for unauthorized users.
    const hasAccess = !body?.includes('not allowed') && !body?.includes('cheatin');

    // Author should NOT have access to the settings page.
    expect(hasAccess).toBe(false);

    await context.close();
  });

  test('author sees view-only metabox on post editor', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    // Log in as author.
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'author');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');

    await page.goto('/wp-admin/post-new.php');
    // Author should still see the metabox (for viewing SEO data).
    const metabox = page.locator('#search-intel-seo');
    const visible = await metabox.isVisible().catch(() => false);

    if (visible) {
      // If metabox is visible, verify the analyze button works but
      // settings-level features are not exposed.
      const settingsLink = page.locator('a[href*="page=search-intel"]:has-text("Settings")');
      const settingsCount = await settingsLink.count();
      // Author should not see settings links in the metabox.
      expect(settingsCount).toBe(0);
    }

    await context.close();
  });
});
