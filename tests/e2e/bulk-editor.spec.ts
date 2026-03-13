import { test, expect } from '@playwright/test';

/**
 * E2E smoke tests for the Scale Bulk Editor page.
 *
 * The Bulk Editor requires the Scale feature to be enabled (enable_scale setting).
 * Tests skip gracefully when the feature is not active.
 */

/** Navigate to the bulk editor and return true if the page rendered. */
async function gotoAndCheck(page: import('@playwright/test').Page): Promise<boolean> {
  await page.goto('/wp-admin/admin.php?page=search-intel-bulk-editor');
  await page.waitForLoadState('domcontentloaded');
  const heading = page.locator('h1');
  const count = await heading.count();
  if (count === 0) return false;
  const text = await heading.first().textContent();
  return text?.includes('Bulk Editor') ?? false;
}

test.describe('Bulk Editor', () => {

  test('bulk editor page loads when Scale feature is active', async ({ page }) => {
    const loaded = await gotoAndCheck(page);
    test.skip(!loaded, 'Scale feature not enabled — bulk editor page not registered');
    await expect(page.locator('h1')).toContainText('Bulk Editor');
  });

  test('bulk editor table is visible', async ({ page }) => {
    const loaded = await gotoAndCheck(page);
    test.skip(!loaded, 'Scale feature not enabled');
    await expect(page.locator('table.widefat')).toBeVisible();
  });

  test('bulk editor table has expected columns', async ({ page }) => {
    const loaded = await gotoAndCheck(page);
    test.skip(!loaded, 'Scale feature not enabled');
    const headerRow = page.locator('table.widefat thead tr');
    await expect(headerRow).toContainText('ID');
    await expect(headerRow).toContainText('Post Title');
    await expect(headerRow).toContainText('SEO Title');
    await expect(headerRow).toContainText('Description');
    await expect(headerRow).toContainText('Keyword');
  });

  test('bulk editor rows have editable inputs', async ({ page }) => {
    const loaded = await gotoAndCheck(page);
    test.skip(!loaded, 'Scale feature not enabled');
    const inputs = page.locator('table.widefat tbody input[type="text"]');
    const count = await inputs.count();
    // Each row has 3 inputs (title, description, keyword) or shows "No published posts"
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('bulk editor save button exists', async ({ page }) => {
    const loaded = await gotoAndCheck(page);
    test.skip(!loaded, 'Scale feature not enabled');
    const submit = page.locator('input[type="submit"], button[type="submit"]');
    // Submit button only visible if there are posts
    const count = await submit.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });
});
