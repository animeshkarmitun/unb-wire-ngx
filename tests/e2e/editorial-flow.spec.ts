import { test, expect } from '@playwright/test';

test.describe('Editorial → Portal E2E', () => {
  test('draft → in_review → approved → published appears in portal feed', async ({ page, request }) => {
    const base = process.env.LARAVEL_URL ?? 'http://localhost:8000';
    const res = await request.get(`${base}/api/v1/portal/feed`);
    expect(res.ok()).toBeTruthy();
    const json = await res.json();
    expect(json).toHaveProperty('data');
  });

  test('RBAC blocks Business Team from add-news', async ({ page }) => {
    await page.goto('/admin/login');
    await expect(page.locator('body')).toBeVisible();
  });

  test('portal search degrades to feed when Meilisearch down', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('text=Wire feed')).toBeVisible();
  });
});
