import { test, expect } from '@playwright/test';

/**
 * E2E smoke tests for the Scale Bulk Editor page.
 */

test.describe('Bulk Editor', () => {

  test('bulk editor page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-bulk-editor');
    await expect(page.locator('h1')).toContainText('Bulk Editor');
  });

  test('bulk editor table is visible', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-bulk-editor');
    const table = page.locator('table.widefat');
    await expect(table).toBeVisible();
  });

  test('bulk editor table has expected columns', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-bulk-editor');
    const headerRow = page.locator('table.widefat thead tr');
    await expect(headerRow).toContainText('ID');
    await expect(headerRow).toContainText('Post Title');
    await expect(headerRow).toContainText('SEO Title');
    await expect(headerRow).toContainText('Description');
    await expect(headerRow).toContainText('Keyword');
  });

  test('bulk editor rows have editable inputs', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-bulk-editor');
    const inputs = page.locator('table.widefat tbody input[type="text"]');
    const count = await inputs.count();
    // Each row has 3 inputs: title, description, keyword
    expect(count).toBeGreaterThanOrEqual(3);
  });

  test('bulk editor save button exists', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-bulk-editor');
    const submit = page.locator('input[type="submit"], button[type="submit"]');
    await expect(submit).toBeVisible();
  });

  test('bulk editor saves changes', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-bulk-editor');

    // Find the first SEO title input and set a value.
    const firstTitleInput = page.locator('table.widefat tbody input[type="text"]').first();
    await firstTitleInput.fill('E2E Bulk Edit Test Title');

    // Submit the form.
    await page.click('input[type="submit"], button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Verify the value persisted.
    const updatedInput = page.locator('table.widefat tbody input[type="text"]').first();
    await expect(updatedInput).toHaveValue('E2E Bulk Edit Test Title');
  });
});
