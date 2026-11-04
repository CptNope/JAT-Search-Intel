import { test, expect } from '@playwright/test';

/**
 * E2E tests for custom score columns in the post list table.
 */

test.describe('Post List Columns', () => {

  test('JAT Search Intel column header is visible', async ({ page }) => {
    await page.goto('/wp-admin/edit.php');
    const header = page.locator('th#si_scores');
    await expect(header).toBeVisible();
  });

  test('score column renders for published posts', async ({ page }) => {
    await page.goto('/wp-admin/edit.php');
    const cells = page.locator('td.column-si_scores, td.si_scores');
    const count = await cells.count();
    expect(count).toBeGreaterThan(0);
  });

  test('score column shows score data', async ({ page }) => {
    await page.goto('/wp-admin/edit.php');
    const cell = page.locator('td.column-si_scores, td.si_scores').first();
    const text = await cell.textContent();
    // Should contain a score like "3/4" or "0/4" or a dash
    expect(text?.trim()).toMatch(/\d|—|–|-/);
  });
});
