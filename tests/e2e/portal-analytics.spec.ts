import { test, expect } from '@playwright/test';

test.describe('Portal GA4 analytics (M13-GA-001)', () => {
  test('portal loads no GA script when GA_MEASUREMENT_ID is unset', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('script[src*="googletagmanager.com/gtag/js"]')).toHaveCount(0);
  });

  test('story reader loads no GA script when unconfigured', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    const firstLink = page.locator('a[href^="/story/"]').first();
    if (await firstLink.count()) {
      await firstLink.click();
      await expect(page.locator('script[src*="googletagmanager.com/gtag/js"]')).toHaveCount(0);
    }
  });

  test('admin login page carries no GA script', async ({ page }) => {
    await page.goto('http://localhost:8000/login');
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('script[src*="googletagmanager.com/gtag/js"]')).toHaveCount(0);
  });
});
