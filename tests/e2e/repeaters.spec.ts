import { test, expect } from '@playwright/test';

/**
 * E2E tests for FAQ, Entity, and Citation repeater fields in the metabox.
 */

test.describe('Repeaters', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await expect(page.locator('#jat-search-intel-seo')).toBeVisible({ timeout: 10_000 });
  });

  // ── FAQ repeater ──────────────────────────────────────────────────

  test('FAQ tab shows add button', async ({ page }) => {
    await page.click('.si-tab[data-tab="aeo"]');
    const addBtn = page.locator('.si-add-faq, #si-add-faq');
    await expect(addBtn).toBeVisible();
  });

  test('FAQ add button creates new row', async ({ page }) => {
    await page.click('.si-tab[data-tab="aeo"]');
    await page.click('#si-add-faq');
    const rows = page.locator('.si-faq-item-row');
    await expect(rows.first()).toBeVisible();
  });

  test('FAQ row has question and answer fields', async ({ page }) => {
    await page.click('.si-tab[data-tab="aeo"]');
    await page.click('#si-add-faq');
    await expect(page.locator('.si-faq-item-row .si-faq-question')).toBeVisible();
    await expect(page.locator('.si-faq-item-row .si-faq-answer')).toBeVisible();
  });

  test('FAQ row can be removed', async ({ page }) => {
    await page.click('.si-tab[data-tab="aeo"]');
    await page.click('#si-add-faq');
    await expect(page.locator('.si-faq-item-row')).toBeVisible();
    await page.click('.si-faq-item-row .si-remove-faq');
    await expect(page.locator('.si-faq-item-row')).toHaveCount(0);
  });

  test('multiple FAQ rows can be added', async ({ page }) => {
    await page.click('.si-tab[data-tab="aeo"]');
    await page.click('#si-add-faq');
    await page.click('#si-add-faq');
    await page.click('#si-add-faq');
    const rows = page.locator('.si-faq-item-row');
    const count = await rows.count();
    expect(count).toBeGreaterThanOrEqual(3);
  });

  // ── Entity repeater ───────────────────────────────────────────────

  test('Entity tab shows add button', async ({ page }) => {
    await page.click('.si-tab[data-tab="geo"]');
    const addBtn = page.locator('.si-add-entity, #si-add-entity');
    await expect(addBtn).toBeVisible();
  });

  test('Entity add creates row with name/type/url', async ({ page }) => {
    await page.click('.si-tab[data-tab="geo"]');
    await page.click('#si-add-entity');
    const row = page.locator('.si-entity-row').first();
    await expect(row).toBeVisible();
  });

  test('Entity row can be removed', async ({ page }) => {
    await page.click('.si-tab[data-tab="geo"]');
    await page.click('#si-add-entity');
    await expect(page.locator('.si-entity-row')).toBeVisible();
    await page.click('.si-entity-row .si-remove-entity');
    await expect(page.locator('.si-entity-row')).toHaveCount(0);
  });

  // ── Citation repeater ─────────────────────────────────────────────

  test('Citation tab shows add button', async ({ page }) => {
    await page.click('.si-tab[data-tab="aeo"]');
    const addBtn = page.locator('#si-add-citation');
    await expect(addBtn).toBeVisible();
  });

  test('Citation add creates row with source/url/date', async ({ page }) => {
    await page.click('.si-tab[data-tab="aeo"]');
    await page.click('#si-add-citation');
    const row = page.locator('.si-citation-row').first();
    await expect(row).toBeVisible();
  });

  test('Citation row can be removed', async ({ page }) => {
    await page.click('.si-tab[data-tab="aeo"]');
    await page.click('#si-add-citation');
    await expect(page.locator('.si-citation-row')).toBeVisible();
    await page.click('.si-citation-row .si-remove-citation');
    await expect(page.locator('.si-citation-row')).toHaveCount(0);
  });
});
