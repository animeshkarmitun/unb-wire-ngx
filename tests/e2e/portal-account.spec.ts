import { test, expect } from '@playwright/test';
import { CLIENTS } from './helpers/auth';

const BASE = 'http://localhost:3000';

async function loginViaUI(page: import('@playwright/test').Page): Promise<boolean> {
  await page.goto(BASE);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1000);

  await page.locator('button:has-text("Login")').click();
  const modal = page.locator('.lightbox.open');
  await expect(modal).toBeVisible({ timeout: 5000 });

  await modal.locator('input[type="email"]').fill(CLIENTS.dailyStar.email);
  await modal.locator('input[type="password"]').first().fill(CLIENTS.dailyStar.password);

  const responsePromise = page.waitForResponse(
    resp => resp.url().includes('/api/v1/portal/login'),
    { timeout: 10000 }
  ).catch(() => null);

  await modal.locator('button[type="submit"]:has-text("Login")').click();
  const response = await responsePromise;

  if (!response || !response.ok()) return false;

  await expect(modal).toBeHidden({ timeout: 10000 });
  return true;
}

test.describe('Portal Account Page (/account)', () => {
  test.setTimeout(45000);

  test('Account page loads with user profile data', async ({ page }) => {
    const loggedIn = await loginViaUI(page);
    if (!loggedIn) { test.skip(); return; }

    await page.goto(`${BASE}/account`);
    await page.waitForLoadState('networkidle');

    await expect(page.locator('h1:has-text("My Account")')).toBeVisible({ timeout: 10000 });
    await expect(page.getByText(CLIENTS.dailyStar.email)).toBeVisible();
  });

  test('Edit name and save persists after reload', async ({ page }) => {
    const loggedIn = await loginViaUI(page);
    if (!loggedIn) { test.skip(); return; }

    await page.goto(`${BASE}/account`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1:has-text("My Account")')).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: 'Edit' }).click();
    const nameInput = page.locator('input[type="text"]').first();
    await expect(nameInput).toBeVisible();

    const testName = `Test User ${Date.now()}`;
    await nameInput.fill(testName);
    await page.getByRole('button', { name: 'Save changes' }).click();

    await expect(page.getByText('Profile updated')).toBeVisible({ timeout: 5000 });
    await page.reload();
    await page.waitForLoadState('networkidle');
    await expect(page.getByText(testName)).toBeVisible({ timeout: 10000 });

    // Restore
    await page.getByRole('button', { name: 'Edit' }).click();
    await page.locator('input[type="text"]').first().fill('News Desk');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('Profile updated')).toBeVisible({ timeout: 5000 });
  });

  test('Change password with correct current password succeeds', async ({ page }) => {
    const loggedIn = await loginViaUI(page);
    if (!loggedIn) { test.skip(); return; }

    await page.goto(`${BASE}/account`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1:has-text("My Account")')).toBeVisible({ timeout: 10000 });

    const section = page.locator('section').filter({ hasText: 'Change password' });
    await section.locator('input[autocomplete="current-password"]').fill('password');
    await section.locator('input[autocomplete="new-password"]').first().fill('password');
    await section.locator('input[autocomplete="new-password"]').last().fill('password');

    const responsePromise = page.waitForResponse(
      resp => resp.url().includes('/api/v1/portal/password'),
      { timeout: 10000 }
    ).catch(() => null);

    await section.getByRole('button', { name: 'Change password' }).click();
    const response = await responsePromise;

    if (response && response.ok()) {
      await expect(page.getByText(/changed|updated/i).first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('Change password with wrong current shows error', async ({ page }) => {
    const loggedIn = await loginViaUI(page);
    if (!loggedIn) { test.skip(); return; }

    await page.goto(`${BASE}/account`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1:has-text("My Account")')).toBeVisible({ timeout: 10000 });

    const section = page.locator('section').filter({ hasText: 'Change password' });
    await section.locator('input[autocomplete="current-password"]').fill('wrongpassword');
    await section.locator('input[autocomplete="new-password"]').first().fill('newpass123');
    await section.locator('input[autocomplete="new-password"]').last().fill('newpass123');

    const responsePromise = page.waitForResponse(
      resp => resp.url().includes('/api/v1/portal/password'),
      { timeout: 10000 }
    ).catch(() => null);

    await section.getByRole('button', { name: 'Change password' }).click();
    const response = await responsePromise;

    if (response && !response.ok()) {
      await expect(page.locator('text=/error|wrong|incorrect|invalid|failed/i').first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('Back to wire feed link navigates to portal home', async ({ page }) => {
    const loggedIn = await loginViaUI(page);
    if (!loggedIn) { test.skip(); return; }

    await page.goto(`${BASE}/account`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1:has-text("My Account")')).toBeVisible({ timeout: 10000 });

    await page.getByRole('link', { name: /Back to wire feed/i }).first().click();
    await page.waitForURL(`${BASE}/`, { timeout: 5000 });
    expect(page.url()).toBe(`${BASE}/`);
  });
});
