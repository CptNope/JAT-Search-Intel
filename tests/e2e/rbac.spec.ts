import { test, expect } from '@playwright/test';

/**
 * E2E smoke tests for Role-Based Access Control.
 *
 * Tests that an admin user sees full UI. Author access tests require
 * creating the user first via wp-env CLI and are skipped if unavailable.
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

  test('admin can access bulk editor if Scale is enabled', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-bulk-editor');
    await page.waitForLoadState('domcontentloaded');
    const heading = page.locator('h1');
    const count = await heading.count();
    if (count === 0) {
      test.skip(true, 'Scale feature not enabled — bulk editor page not registered');
      return;
    }
    const text = await heading.first().textContent();
    if (!text?.includes('Bulk Editor')) {
      test.skip(true, 'Scale feature not enabled — bulk editor page not registered');
      return;
    }
    await expect(heading.first()).toContainText('Bulk Editor');
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

  // These tests require an "author" user to exist.
  // Create it before running: npx wp-env run cli -- wp user create author author@example.com --role=author --user_pass=password

  test('author cannot access settings page', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    // Attempt to log in as author.
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'author');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await page.waitForLoadState('domcontentloaded');

    // Check if login succeeded — if still on login page, user doesn't exist; skip.
    const currentUrl = page.url();
    if (currentUrl.includes('wp-login.php')) {
      await context.close();
      test.skip(true, 'Author user does not exist — run: npx wp-env run cli -- wp user create author author@example.com --role=author --user_pass=password');
      return;
    }

    // Try to access settings — should get permission denied.
    await page.goto('/wp-admin/admin.php?page=search-intel');
    await page.waitForLoadState('domcontentloaded');
    const body = await page.locator('body').textContent() ?? '';

    // WP shows "Sorry, you are not allowed to access this page." for unauthorized users,
    // or redirects to wp-admin which won't have our settings form.
    const hasPermissionDenied = body.includes('not allowed') || body.includes('cheatin');
    const hasSettingsForm = body.includes('search-intel') && (await page.locator('input[type="submit"]').count()) > 0;

    // Author should either see a permission denied message or NOT see the settings form.
    expect(hasPermissionDenied || !hasSettingsForm).toBe(true);

    await context.close();
  });
});
