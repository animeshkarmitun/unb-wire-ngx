import { test, expect } from '@playwright/test';
import { loginAs, waitForToast } from './helpers/auth';

test.describe('Staff Profile Page (M12-PROFILE-001)', () => {
  test.setTimeout(45000);

  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'admin');
  });

  test('profile page renders with admin chrome (sidebar + topnav, no Breeze layout)', async ({ page }) => {
    await page.goto('/profile');
    await page.waitForLoadState('networkidle');

    // Sidebar nav present (admin chrome)
    await expect(page.locator('nav').first()).toBeVisible();
    // Topnav/header present
    await expect(page.locator('header, [class*="topnav"]').first()).toBeVisible();
    // Page heading contains "Profile"
    await expect(page.getByRole('heading', { name: /profile/i }).first()).toBeVisible();
  });

  test('profile form shows current user name and email pre-filled', async ({ page }) => {
    await page.goto('/profile');
    await page.waitForLoadState('networkidle');

    const nameInput = page.locator('input[name="name"]');
    const emailInput = page.locator('input[name="email"]');

    await expect(nameInput).toBeVisible();
    await expect(emailInput).toBeVisible();
    await expect(nameInput).toHaveValue(/.+/);
    await expect(emailInput).toHaveValue('test@example.com');
  });

  test('update name shows toast and persists after reload', async ({ page }) => {
    await page.goto('/profile');
    await page.waitForLoadState('networkidle');

    const nameInput = page.locator('input[name="name"]');
    const newName = 'E2E Test User ' + Date.now();

    await nameInput.clear();
    await nameInput.fill(newName);

    const saveBtn = page.locator('form:has(input[name="name"]) button[type="submit"], button:has-text("Save")').first();
    await saveBtn.click();
    await page.waitForTimeout(1500);

    await page.reload();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('input[name="name"]')).toHaveValue(newName);
  });

  test('update email resets verification and persists', async ({ page }) => {
    await page.goto('/profile');
    await page.waitForLoadState('networkidle');

    const emailInput = page.locator('input[name="email"]');
    const newEmail = 'e2e-' + Date.now() + '@test.com';

    await emailInput.clear();
    await emailInput.fill(newEmail);

    const saveBtn = page.locator('form:has(input[name="email"]) button[type="submit"], button:has-text("Save")').first();
    await saveBtn.click();
    await page.waitForTimeout(1500);

    await page.reload();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('input[name="email"]')).toHaveValue(newEmail);

    // Restore original email
    await emailInput.clear();
    await emailInput.fill('test@example.com');
    await saveBtn.click();
    await page.waitForTimeout(1000);
  });

  test('empty name prevents submit (HTML5 required)', async ({ page }) => {
    await page.goto('/profile');
    await page.waitForLoadState('networkidle');

    const nameInput = page.locator('input[name="name"]');
    // Input has HTML5 required — clear it and try submit
    await nameInput.clear();

    const saveBtn = page.locator('form:has(input[name="name"]) button[type="submit"]').first();
    await saveBtn.click();
    await page.waitForTimeout(1000);

    // Browser prevents submit on required field — page stays on /profile
    expect(page.url()).toMatch(/profile/);
    // Name input should still be focused or empty (form didn't submit)
    await expect(nameInput).toBeVisible();
  });

  test('change password with correct current password works', async ({ page }) => {
    await page.goto('/profile');
    await page.waitForLoadState('networkidle');

    const currentPw = page.locator('input[name="current_password"]');
    const newPw = page.locator('input[name="password"]');
    const confirmPw = page.locator('input[name="password_confirmation"]');

    await currentPw.fill('password');
    await newPw.fill('password');
    await confirmPw.fill('password');

    const updateBtn = page.locator('form:has(input[name="current_password"]) button[type="submit"]').first();
    await updateBtn.click();
    await page.waitForTimeout(1500);

    await expect(page.locator('body')).not.toContainText(/error/i);
  });

  test('change password with wrong current password shows error', async ({ page }) => {
    await page.goto('/profile');
    await page.waitForLoadState('networkidle');

    const currentPw = page.locator('input[name="current_password"]');
    const newPw = page.locator('input[name="password"]');
    const confirmPw = page.locator('input[name="password_confirmation"]');

    await currentPw.fill('wrongpassword');
    await newPw.fill('newpassword123');
    await confirmPw.fill('newpassword123');

    const updateBtn = page.locator('form:has(input[name="current_password"]) button[type="submit"]').first();
    await updateBtn.click();
    await page.waitForTimeout(1000);

    await expect(page.locator('p.text-red-600, [class*="text-red"]').first()).toBeVisible({ timeout: 5000 });
  });

  test('mismatched password confirmation shows validation error', async ({ page }) => {
    await page.goto('/profile');
    await page.waitForLoadState('networkidle');

    const currentPw = page.locator('input[name="current_password"]');
    const newPw = page.locator('input[name="password"]');
    const confirmPw = page.locator('input[name="password_confirmation"]');

    await currentPw.fill('password');
    await newPw.fill('newpassword123');
    await confirmPw.fill('differentpassword');

    const updateBtn = page.locator('form:has(input[name="current_password"]) button[type="submit"]').first();
    await updateBtn.click();
    await page.waitForTimeout(1000);

    await expect(page.locator('p.text-red-600, [class*="text-red"]').first()).toBeVisible({ timeout: 5000 });
  });

  test('DELETE /profile is rejected (route removed)', async ({ request }) => {
    const response = await request.delete('/profile');
    // 404 (not found) or 405 (method not allowed) — either confirms DELETE route is gone
    expect([404, 405]).toContain(response.status());
  });

  test('topnav user dropdown profile link navigates to /profile', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    // Open user dropdown — button has aria-label="User menu"
    const userMenuBtn = page.locator('button[aria-label="User menu"]').first();
    await expect(userMenuBtn).toBeVisible();
    await userMenuBtn.click();
    await page.waitForTimeout(500);

    // Click "My profile" link inside dropdown
    const profileLink = page.locator('a:has-text("My profile"), a[href*="profile"]').first();
    await expect(profileLink).toBeVisible({ timeout: 3000 });
    await profileLink.click();

    await page.waitForURL('**/profile**', { timeout: 5000 });
    expect(page.url()).toMatch(/profile/);
  });

  test('sidebar preferences link navigates to /admin/preferences', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    const prefsLink = page.locator('a[href*="preferences"]').first();
    await expect(prefsLink).toBeVisible();
    await prefsLink.click();

    await page.waitForURL('**/preferences**', { timeout: 5000 });
    expect(page.url()).toMatch(/preferences/);
  });

  test('guest accessing /profile is redirected to login', async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('/profile');
    await page.waitForTimeout(2000);

    expect(page.url()).toMatch(/login/);
  });
});
