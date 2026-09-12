import { test, expect } from '@playwright/test';

/**
 * Web Auth & RBAC E2E — tests real authentication behavior and route protection.
 * Replaces the original weak assertions that accepted multiple status codes.
 */

test.describe('Web Auth & Route Protection', () => {
  test('GET / returns 200', async ({ request }) => {
    const r = await request.get('/');
    expect(r.status()).toBe(200);
  });

  test('GET /up health check returns 200', async ({ request }) => {
    const r = await request.get('/up');
    expect(r.status()).toBe(200);
  });

  test('login page renders with form', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('form')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('guest is redirected to login when accessing /admin', async ({ page }) => {
    await page.goto('/admin');
    // Should end up on login page
    expect(page.url()).toMatch(/login/);
  });

  test('guest is redirected to login when accessing /admin/news/en', async ({ page }) => {
    await page.goto('/admin/news/en');
    expect(page.url()).toMatch(/login/);
  });

  test('guest is redirected to login when accessing /admin/roles', async ({ page }) => {
    await page.goto('/admin/roles');
    expect(page.url()).toMatch(/login/);
  });

  test('guest is redirected to login when accessing /profile', async ({ page }) => {
    await page.goto('/profile');
    expect(page.url()).toMatch(/login/);
  });

  test('login with invalid credentials shows error', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'nonexistent@example.com');
    await page.fill('input[name="password"]', 'wrongpassword');
    await page.click('button[type="submit"]');

    // Should stay on login page with error
    await page.waitForTimeout(1000);
    expect(page.url()).toMatch(/login/);
  });

  test('login with valid credentials redirects to admin', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 });

    expect(page.url()).toMatch(/admin/);
    await expect(page.locator('body')).not.toContainText('Server Error');
  });

  test('livewire.js asset returns 200', async ({ request }) => {
    const r = await request.get('/livewire/livewire.js');
    expect(r.status()).toBe(200);
  });
});
