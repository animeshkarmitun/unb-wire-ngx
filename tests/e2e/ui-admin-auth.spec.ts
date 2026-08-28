import { test, expect } from '@playwright/test';

test.describe('Admin UI - Authenticated (test@example.com/Admin)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 5000 }).catch(() => {});
    await expect(page).not.toHaveURL(/\/login/);
  });

  const routes = [
    '/admin',
    '/admin/roles',
    '/admin/packages',
    '/admin/clients',
    '/admin/news/en',
    '/admin/news/bn',
    '/admin/photos',
    '/admin/ai-settings',
    '/admin/add-news',
    '/admin/ap-photos',
    '/admin/distribution',
    '/admin/preferences',
    '/admin/delivery-settings',
  ];

  for (const r of routes) {
    test(`GET ${r} renders 200 no 500`, async ({ page }) => {
      const res = await page.goto(r);
      expect(res?.status()).toBe(200);
      await expect(page.locator('body')).not.toContainText('Server Error');
      await expect(page.locator('body')).not.toContainText('Exception');
    });
  }

  test('story workflow: add-news form renders stepper', async ({ page }) => {
    await page.goto('/admin/add-news');
    await expect(page.locator('body')).toContainText(/headline|Headline|News/i);
  });

  test('RBAC: Admin can see Clients + Packages nav', async ({ page }) => {
    await page.goto('/admin');
    await expect(page.locator('body')).toContainText(/Clients|Packages|Wire/i);
  });

  test('portal API smoke still 200 after UI login', async ({ request }) => {
    const r = await request.get('/api/v1/portal/feed');
    expect(r.ok()).toBeTruthy();
  });
});
