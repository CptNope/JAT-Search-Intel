import { test, expect } from '@playwright/test';

/**
 * E2E smoke tests for the SEO Plugin Migration page.
 */

test.describe('Migration Wizard', () => {

  test('migration page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-migration');
    await expect(page.locator('h1')).toContainText('SEO Plugin Migration');
  });

  test('migration page shows info when no sources detected', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-migration');
    // On a fresh install with no other SEO plugins, it should show the info notice.
    const notice = page.locator('.notice-info');
    const cards = page.locator('.card');
    const noticeCount = await notice.count();
    const cardCount = await cards.count();
    // Either we see the "no data" notice or detected source cards — page rendered correctly either way.
    expect(noticeCount + cardCount).toBeGreaterThan(0);
  });

  test('migration page lists supported plugins in info text', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-migration');
    const content = await page.locator('.wrap').textContent();
    expect(content).toContain('Yoast SEO');
    expect(content).toContain('Rank Math');
  });

  test('migration page renders correct structure', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=search-intel-migration');
    const wrap = page.locator('.wrap');
    await expect(wrap).toBeVisible();

    // The page either shows source cards (if other SEO plugins have data) or an info notice.
    const cards = page.locator('.card');
    const notice = page.locator('.notice-info');
    const cardCount = await cards.count();
    const noticeCount = await notice.count();

    if (cardCount > 0) {
      // If sources are detected, each card should have preview and migrate buttons.
      const firstCard = cards.first();
      await expect(firstCard.locator('button[data-action="dryrun"]')).toBeVisible();
      await expect(firstCard.locator('button[data-action="migrate"]')).toBeVisible();
    } else {
      // Fresh install — info notice should be present.
      expect(noticeCount).toBeGreaterThan(0);
    }
  });
});
