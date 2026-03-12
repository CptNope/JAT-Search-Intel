import { test, expect } from '@playwright/test';

/**
 * E2E tests for the Search Intel post editor metabox.
 * Requires an active WordPress instance with the plugin enabled.
 */

test.describe('Metabox', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await expect(page.locator('#search-intel-metabox')).toBeVisible({ timeout: 10_000 });
  });

  // ── Tab navigation ──────────────────────────────────────────────────

  test('SEO tab is active by default', async ({ page }) => {
    const seoTab = page.locator('.si-tab[data-tab="seo"]');
    await expect(seoTab).toHaveClass(/active/);
  });

  test('all tabs navigate correctly', async ({ page }) => {
    const tabs = ['seo', 'aeo', 'geo', 'social', 'advanced'];
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
    const preview = page.locator('.si-serp-title');
    await expect(preview).toContainText('My SEO Title for Preview');
  });

  test('SERP preview shows description', async ({ page }) => {
    await page.fill('#si_description', 'Meta description text for SERP.');
    const preview = page.locator('.si-serp-description');
    await expect(preview).toContainText('Meta description text for SERP.');
  });

  // ── Character counters ────────────────────────────────────────────

  test('title character counter updates on input', async ({ page }) => {
    await page.fill('#si_title', 'Short');
    const counter = page.locator('.si-title-counter');
    await expect(counter).toContainText('5');
  });

  test('description character counter updates on input', async ({ page }) => {
    await page.fill('#si_description', 'A description.');
    const counter = page.locator('.si-desc-counter');
    await expect(counter).toContainText('14');
  });

  test('title counter warns on overlong title', async ({ page }) => {
    await page.fill('#si_title', 'A'.repeat(70));
    const counter = page.locator('.si-title-counter');
    await expect(counter).toHaveClass(/warning|over/);
  });

  // ── Focus keyword ─────────────────────────────────────────────────

  test('focus keyword field accepts input', async ({ page }) => {
    await page.fill('#si_focus_keyword', 'test keyword');
    await expect(page.locator('#si_focus_keyword')).toHaveValue('test keyword');
  });

  // ── Nonce field present ───────────────────────────────────────────

  test('nonce field is present in metabox', async ({ page }) => {
    const nonce = page.locator('#search_intel_metabox_nonce');
    await expect(nonce).toBeAttached();
  });
});
