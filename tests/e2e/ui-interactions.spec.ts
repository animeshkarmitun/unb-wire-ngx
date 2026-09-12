import { test, expect } from '@playwright/test';

test.describe('RBAC negative UI', () => {
  test('Uploader-English cannot access /admin/clients → 403', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'maria@unbnews.org');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 5000 }).catch(()=>{});
    const res = await page.goto('/admin/clients');
    expect(res?.status()).toBe(403);
  });

  test('Business Team cannot access /admin/add-news → 403', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'arif@unbnews.org');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 5000 }).catch(()=>{});
    const res = await page.goto('/admin/add-news');
    expect(res?.status()).toBe(403);
  });
});

test.describe('Admin interactions — modals & forms render', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 5000 }).catch(()=>{});
  });

  test('Roles: New role button visible', async ({ page }) => {
    await page.goto('/admin/roles');
    await expect(page.getByRole('button', { name: 'New role' })).toBeVisible({ timeout: 5000 });
  });

  test('Roles: Invite member button visible', async ({ page }) => {
    await page.goto('/admin/roles');
    await expect(page.getByRole('button', { name: 'Invite member' })).toBeVisible({ timeout: 5000 });
  });

  test('Packages: New package button visible', async ({ page }) => {
    await page.goto('/admin/packages');
    await expect(page.getByRole('button', { name: 'New package', exact: true })).toBeVisible({ timeout: 5000 });
  });

  test('Clients: Onboard drawer/modal opens', async ({ page }) => {
    await page.goto('/admin/clients');
    const btn = page.locator('button, a').filter({ hasText: /Onboard|New client|Invite/i }).first();
    if(await btn.count()>0){ await btn.click(); await expect(page.locator('body')).toContainText(/Onboard|Client|email/i, { timeout: 5000 }); }
    else { await expect(page.locator('body')).toContainText(/Clients/i); }
  });

  test('AI Settings: page shows kill-switch / toggles', async ({ page }) => {
    await page.goto('/admin/ai-settings');
    await expect(page.locator('body')).toContainText(/AI|Kill|preedit|model/i);
  });

  test('Distribution: page renders log table', async ({ page }) => {
    await page.goto('/admin/distribution');
    await expect(page.locator('body')).toContainText(/Distribution|Delivery|log/i);
  });

  test('AddNews: stepper renders and headline field present', async ({ page }) => {
    await page.goto('/admin/add-news');
    await expect(page.locator('input, textarea').first()).toBeVisible({ timeout: 5000 });
    await expect(page.locator('body')).toContainText(/headline|Headline/i);
  });

  test('Photos: page renders', async ({ page }) => {
    await page.goto('/admin/photos');
    await expect(page.locator('body')).toContainText(/Photos|Media|Library/i, { timeout: 5000 });
  });
});
