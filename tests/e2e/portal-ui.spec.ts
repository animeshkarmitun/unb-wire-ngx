import { test, expect } from '@playwright/test';

test.describe('Portal UI 3000', () => {
  test('Wire feed page renders header + search input', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    await expect(page.locator('text=UNB Wire').first()).toBeVisible({ timeout: 15000 });
    await expect(page.locator('text=Wire feed')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#portalSearch')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=How search works')).toBeVisible();
  });

  test('search input accepts typing', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    const input = page.locator('#portalSearch');
    await input.fill('Bangladesh');
    await expect(input).toHaveValue('Bangladesh');
  });

  test('feed shows stories or empty placeholder', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    await expect(page.locator('body')).toContainText(/Wire feed|No stories yet|Latest/i);
  });

  test('header Live badge visible', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    await expect(page.locator('text=Live').first()).toBeVisible();
  });
});
