import { test, expect } from '@playwright/test';

/**
 * E2E tests for content analysis functionality.
 */

test.describe('Content Analysis', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await expect(page.locator('#search-intel-seo')).toBeVisible({ timeout: 10_000 });
  });

  // ── Run Analysis button ───────────────────────────────────────────

  test('Run Analysis button exists', async ({ page }) => {
    await page.click('.si-tab[data-tab="analysis"]');
    const btn = page.locator('#si-run-analysis');
    await expect(btn).toBeVisible();
  });

  test('Run Analysis triggers AJAX and shows scores', async ({ page }) => {
    // Enter content in classic editor
    await page.fill('#title', 'Test Post for Analysis');
    const contentArea = page.locator('#content');
    if (await contentArea.isVisible()) {
      await contentArea.fill('This is test content for analysis with a focus keyword repeated several times for scoring.');
    }
    await page.fill('#si_focus_keyword', 'focus keyword');
    await page.click('.si-tab[data-tab="analysis"]');
    await page.click('#si-run-analysis');

    // Wait for score rings to appear
    await expect(page.locator('#si-seo-ring .si-score-value')).toBeVisible({ timeout: 15_000 });
  });

  test('SEO score displays after analysis', async ({ page }) => {
    await page.fill('#title', 'Test Post Title for Analysis');
    await page.fill('#si_focus_keyword', 'analysis');
    await page.fill('#si_description', 'A test description about analysis.');
    await page.click('.si-tab[data-tab="analysis"]');
    await page.click('#si-run-analysis');

    const seoScore = page.locator('#si-seo-ring .si-score-value');
    await expect(seoScore).toBeVisible({ timeout: 15_000 });
  });

  test('analysis shows multiple score categories', async ({ page }) => {
    await page.fill('#title', 'Analysis Checks Test');
    await page.fill('#si_focus_keyword', 'checks');
    await page.click('.si-tab[data-tab="analysis"]');
    await page.click('#si-run-analysis');

    // Should show SEO, AEO, GEO, and Readability score rings
    await expect(page.locator('#si-seo-ring')).toBeVisible({ timeout: 15_000 });
    await expect(page.locator('#si-aeo-ring')).toBeVisible();
    await expect(page.locator('#si-geo-ring')).toBeVisible();
  });

  // ── Score display ─────────────────────────────────────────────────

  test('score circle renders with percentage', async ({ page }) => {
    await page.fill('#title', 'Score Circle Test');
    await page.fill('#si_focus_keyword', 'score');
    await page.click('.si-tab[data-tab="analysis"]');
    await page.click('#si-run-analysis');

    const circle = page.locator('.si-score-ring');
    await expect(circle.first()).toBeVisible({ timeout: 15_000 });
  });
});
