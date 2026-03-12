import { test, expect } from '@playwright/test';

/**
 * E2E tests for content analysis functionality.
 */

test.describe('Content Analysis', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await expect(page.locator('#search-intel-metabox')).toBeVisible({ timeout: 10_000 });
  });

  // ── Run Analysis button ───────────────────────────────────────────

  test('Run Analysis button exists', async ({ page }) => {
    const btn = page.locator('#si-run-analysis, .si-run-analysis');
    await expect(btn).toBeVisible();
  });

  test('Run Analysis triggers AJAX and shows scores', async ({ page }) => {
    // Enter some content first
    const editor = page.locator('#content, .editor-styles-wrapper');
    if (await editor.isVisible()) {
      await editor.click();
      await page.keyboard.type('This is test content for analysis with a focus keyword.');
    }
    await page.fill('#si_focus_keyword', 'focus keyword');
    await page.click('#si-run-analysis, .si-run-analysis');

    // Wait for results to appear
    await expect(page.locator('.si-score, .si-seo-score')).toBeVisible({ timeout: 10_000 });
  });

  test('SEO score displays after analysis', async ({ page }) => {
    await page.fill('#title, #post_title', 'Test Post Title for Analysis');
    await page.fill('#si_focus_keyword', 'analysis');
    await page.fill('#si_description', 'A test description about analysis.');
    await page.click('#si-run-analysis, .si-run-analysis');

    const seoScore = page.locator('.si-seo-score, [data-score="seo"]');
    await expect(seoScore).toBeVisible({ timeout: 10_000 });
  });

  test('check list items appear after analysis', async ({ page }) => {
    await page.fill('#title, #post_title', 'Analysis Checks Test');
    await page.fill('#si_focus_keyword', 'checks');
    await page.click('#si-run-analysis, .si-run-analysis');

    const checks = page.locator('.si-check-item, .si-checklist li');
    await expect(checks.first()).toBeVisible({ timeout: 10_000 });
  });

  // ── Score display ─────────────────────────────────────────────────

  test('score circle renders with percentage', async ({ page }) => {
    await page.fill('#title, #post_title', 'Score Circle Test');
    await page.fill('#si_focus_keyword', 'score');
    await page.click('#si-run-analysis, .si-run-analysis');

    const circle = page.locator('.si-score-circle, .si-score-ring');
    await expect(circle).toBeVisible({ timeout: 10_000 });
  });
});
