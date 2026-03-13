import { test, expect } from '@playwright/test';

/**
 * E2E tests for Search Intel settings pages.
 */

test.describe('Settings Pages', () => {

  // ── Navigation ────────────────────────────────────────────────────

  test('settings menu item exists in admin sidebar', async ({ page }) => {
    await page.goto('/wp-admin/');
    const menuItem = page.locator('#adminmenu a:has-text("Search Intel")');
    await expect(menuItem).toBeVisible();
  });

  test('main settings page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel');
    await expect(page.locator('h1').first()).toContainText(/Search Intel/);
  });

  test('SEO settings tab loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-seo');
    await expect(page.locator('form')).toBeVisible();
  });

  test('Social settings tab loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-social');
    await expect(page.locator('form')).toBeVisible();
  });

  test('AEO settings tab loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-aeo');
    await expect(page.locator('form')).toBeVisible();
  });

  test('GEO settings tab loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-geo');
    await expect(page.locator('form')).toBeVisible();
  });

  test('Sitemap settings tab loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-sitemap');
    await expect(page.locator('form')).toBeVisible();
  });

  // ── SEO settings save ────────────────────────────────────────────

  test('separator setting saves correctly', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel');
    await page.selectOption('select[name="search_intel_settings[separator]"]', '-');
    await page.click('input[type="submit"]');
    // Page redirects back after save — verify the value persisted
    await page.waitForLoadState('networkidle');
    await expect(page.locator('select[name="search_intel_settings[separator]"]')).toHaveValue('-');
  });

  test('enable OG toggle saves', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-seo');
    const checkbox = page.locator('input[name="search_intel_settings[enable_og]"]');
    const wasChecked = await checkbox.isChecked();
    if (wasChecked) {
      await checkbox.uncheck();
    } else {
      await checkbox.check();
    }
    await page.click('input[type="submit"]');
    await page.waitForLoadState('networkidle');
    const nowChecked = await page.locator('input[name="search_intel_settings[enable_og]"]').isChecked();
    expect(nowChecked).not.toBe(wasChecked);
  });

  // ── Sitemap settings save ────────────────────────────────────────

  test('sitemap max URLs setting saves', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-sitemap');
    await page.fill('input[name="search_intel_settings[sitemap_max_urls]"]', '5000');
    await page.click('input[type="submit"]');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('input[name="search_intel_settings[sitemap_max_urls]"]')).toHaveValue('5000');
  });
});
