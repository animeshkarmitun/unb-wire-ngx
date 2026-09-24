import { test, expect } from '@playwright/test';

test.describe('Portal local-time + Meilisearch wiring (FR-PRT-003/007)', () => {
  test('story reader shows consumer time with Dhaka secondary label', async ({ page }) => {
    await page.goto('http://localhost:3000/story/1');
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
    test.skip((await page.locator('text=Story not found').count()) > 0, 'story 1 not present');
    await expect(page.locator('[data-testid="local-time"]').first()).toBeVisible({ timeout: 15000 });
    await expect(page.locator('[data-testid="local-time"]').first()).toContainText(/Dhaka/i);
  });

  test('feed clock shows Dhaka as secondary label', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('text=Dhaka').first()).toBeVisible();
  });

  test('search shows match count and survives zero-result query', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    const input = page.locator('#omniInput, #portalSearch').first();
    await input.fill('Rizvi');
    await expect(page.locator('body')).toContainText(/Rizvi/i, { timeout: 10000 });
    await input.fill('zzzzunlikelyzzzz');
    await expect(page.locator('body')).toContainText(/0 stor|no stor|No stories/i, { timeout: 10000 });
  });
});
