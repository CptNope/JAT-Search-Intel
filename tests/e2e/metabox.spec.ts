import { test, expect } from '@playwright/test';

/**
 * E2E tests for the JAT Search Intel post editor metabox.
 * Requires an active WordPress instance with the plugin enabled.
 */

test.describe('Metabox', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await expect(page.locator('#jat-search-intel-seo')).toBeVisible({ timeout: 10_000 });
  });

  // ── Tab navigation ──────────────────────────────────────────────────

  test('SEO tab is active by default', async ({ page }) => {
    const seoTab = page.locator('.si-tab[data-tab="seo"]');
    await expect(seoTab).toHaveClass(/si-tab-active/);
  });

  test('all tabs navigate correctly', async ({ page }) => {
    const tabs = ['seo', 'aeo', 'geo', 'advanced', 'analysis'];
    for (const tab of tabs) {
      await page.click(`.si-tab[data-tab="${tab}"]`);
      const panel = page.locator(`.si-tab-content[data-tab="${tab}"]`);
      await expect(panel).toBeVisible();
    }
  });

  test('clicking a tab hides other panels', async ({ page }) => {
    await page.click('.si-tab[data-tab="aeo"]');
    const seoPanel = page.locator('.si-tab-content[data-tab="seo"]');
    await expect(seoPanel).toBeHidden();
  });

  // ── SERP preview ──────────────────────────────────────────────────

  test('SERP preview shows title', async ({ page }) => {
    await page.fill('#si_title', 'My SEO Title for Preview');
    const preview = page.locator('#si-serp-title');
    await expect(preview).toContainText('My SEO Title for Preview');
  });

  test('SERP preview shows description', async ({ page }) => {
    await page.fill('#si_description', 'Meta description text for SERP.');
    const preview = page.locator('#si-serp-desc');
    await expect(preview).toContainText('Meta description text for SERP.');
  });

  // ── Character counters ────────────────────────────────────────────

  test('title character counter updates on input', async ({ page }) => {
    await page.fill('#si_title', 'Short');
    const counter = page.locator('.si-char-count').first();
    await expect(counter).toContainText('5');
  });

  test('description character counter updates on input', async ({ page }) => {
    await page.fill('#si_description', 'A description.');
    const counter = page.locator('.si-char-count').nth(1);
    await expect(counter).toContainText('14');
  });

  test('title counter shows over-limit for long title', async ({ page }) => {
    await page.fill('#si_title', 'A'.repeat(70));
    const counter = page.locator('.si-char-count').first();
    // Counter should show 70/60 indicating over the 60 char limit
    await expect(counter).toContainText('70/60');
  });

  // ── Focus keyword ─────────────────────────────────────────────────

  test('focus keyword field accepts input', async ({ page }) => {
    await page.fill('#si_focus_keyword', 'test keyword');
    await expect(page.locator('#si_focus_keyword')).toHaveValue('test keyword');
  });

  // ── Nonce field present ───────────────────────────────────────────

  test('nonce field is present in metabox', async ({ page }) => {
    const nonce = page.locator('#jat_search_intel_metabox_nonce');
    await expect(nonce).toBeAttached();
  });
});
