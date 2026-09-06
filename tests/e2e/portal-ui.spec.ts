import { test, expect } from '@playwright/test';

test.describe('Portal UI 3000', () => {
  test('Wire feed page renders header + search input', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    await expect(page.locator('text=UNB Wire').first()).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#omniInput, #portalSearch')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=News wire')).toBeVisible();
  });

  test('search input accepts typing', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    const input = page.locator('#omniInput, #portalSearch').first();
    await input.fill('Bangladesh');
    await expect(input).toHaveValue('Bangladesh');
  });

  test('feed shows stories or empty placeholder', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    await expect(page.locator('body')).toContainText(/Rizvi|Bangla QR|Swapon|stories|No stories/i);
  });

  test('header Live badge visible', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    await expect(page.locator('text=Live').first()).toBeVisible();
  });
});
