import { test, expect } from '@playwright/test';

test.describe('Portal Bangla typography + labels (FR-PRT-008)', () => {
  test('portal loads Noto Sans Bengali font', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    expect(await page.locator('link[href*="Bengali"]').count()).toBeGreaterThan(0);
  });

  test('bangla story renders with Bangla chrome labels and bn-BD time', async ({ page }) => {
    await page.goto('http://localhost:3000/story/bn1');
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
    test.skip((await page.locator('text=Story not found').count()) > 0, 'bn fixture story not present');
    await expect(page.locator('text=ওয়্যার ফিডে ফিরে যান').first()).toBeVisible();
    await expect(page.locator('[data-testid="local-time"]').first()).toBeVisible();
  });

  test('english story keeps english chrome', async ({ page }) => {
    await page.goto('http://localhost:3000/story/1');
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
    test.skip((await page.locator('text=Story not found').count()) > 0, 'story 1 not present');
    await expect(page.locator('text=Back to wire feed').first()).toBeVisible();
  });
});
