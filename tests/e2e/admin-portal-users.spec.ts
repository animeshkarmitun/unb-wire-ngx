import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

const BASE = 'http://localhost:8000';

test.describe('Admin Portal Users (M12-PROFILE-005)', () => {
  test.setTimeout(60000);

  async function openDrawer(page: import('@playwright/test').Page) {
    await page.goto(`${BASE}/admin/clients`);
    await page.waitForLoadState('networkidle');
    const firstRow = page.locator('#clList .cl-row:not(.head)').first();
    await firstRow.click();
    const drawer = page.locator('#drawer');
    await expect(drawer).toHaveClass(/open/, { timeout: 10000 });
    return drawer;
  }

  async function switchToPortalUsersTab(page: import('@playwright/test').Page, drawer: import('@playwright/test').Locator) {
    await drawer.locator('.dr-tab[data-dtab="portal-users"]').click();
    await page.waitForTimeout(1500);
  }

  // 1
  test('Clients drawer Portal Users tab is visible', async ({ page }) => {
    await loginAs(page, 'admin');
    const drawer = await openDrawer(page);

    const tab = drawer.locator('.dr-tab[data-dtab="portal-users"]');
    await expect(tab).toBeVisible();
    await expect(tab).toContainText('Portal Users');
  });

  // 2
  test('Portal Users tab shows existing client users', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto(`${BASE}/admin/clients`);
    await page.waitForLoadState('networkidle');

    // Click on Daily Star specifically (has seeded portal users)
    const dailyStarRow = page.locator('#clList .cl-row:not(.head):has-text("Daily Star")').first();
    await dailyStarRow.click();
    const drawer = page.locator('#drawer');
    await expect(drawer).toHaveClass(/open/, { timeout: 10000 });

    await switchToPortalUsersTab(page, drawer);

    const body = drawer.locator('#drBody');
    await expect(body).toContainText('Portal Users');

    // Should show at least one portal user
    const userEntries = body.locator('div:has-text("@thedailystar.net")');
    await expect(userEntries.first()).toBeVisible({ timeout: 5000 });
  });

  // 3
  test('Active user shows Deactivate button', async ({ page }) => {
    await loginAs(page, 'admin');
    const drawer = await openDrawer(page);
    await switchToPortalUsersTab(page, drawer);

    const body = drawer.locator('#drBody');
    // Find an active status badge and its sibling Deactivate button
    const deactivateBtn = body.locator('button:has-text("Deactivate")').first();
    await expect(deactivateBtn).toBeVisible({ timeout: 5000 });
  });

  // 4
  test('Invite button opens modal with name/email/role fields', async ({ page }) => {
    await loginAs(page, 'admin');
    const drawer = await openDrawer(page);
    await switchToPortalUsersTab(page, drawer);

    await page.locator('button:has-text("Invite portal user")').click();
    await page.waitForTimeout(1500);

    const modal = page.locator('#portalInviteOverlay');
    await expect(modal).toHaveClass(/open/, { timeout: 5000 });
    await expect(modal.locator('.mo-title')).toContainText('Invite portal user');
    await expect(page.locator('#portalInviteName')).toBeVisible();
    await expect(page.locator('#portalInviteEmail')).toBeVisible();
    await expect(page.locator('#portalInviteRole')).toBeVisible();
    await expect(modal.locator('button:has-text("Send invite")')).toBeVisible();
  });

  // 5
  test('Invite with valid data creates user and shows in list', async ({ page }) => {
    await loginAs(page, 'admin');
    const drawer = await openDrawer(page);
    await switchToPortalUsersTab(page, drawer);

    await page.locator('button:has-text("Invite portal user")').click();
    await page.waitForTimeout(1500);

    const uniqueEmail = `e2e-portal-${Date.now()}@test.com`;
    await page.fill('#portalInviteName', 'E2E Portal Tester');
    await page.fill('#portalInviteEmail', uniqueEmail);

    // Select first available role if options exist
    const roleSelect = page.locator('#portalInviteRole');
    const options = roleSelect.locator('option:not([value=""])');
    if (await options.count() > 0) {
      await roleSelect.selectOption({ index: 1 });
    }

    await page.locator('#portalInviteConfirm').click();
    await page.waitForTimeout(2000);

    // Toast confirmation
    const toast = page.locator('#toastWrap, [class*="toast"]').first();
    await expect(toast).toBeVisible({ timeout: 5000 });

    // User appears in the list with invited badge
    const body = drawer.locator('#drBody');
    await expect(body.locator(`text=${uniqueEmail}`)).toBeVisible({ timeout: 5000 });
    await expect(body.locator('span:has-text("Invited")').first()).toBeVisible();
  });

  // 6
  test('Invite with duplicate email shows validation error', async ({ page }) => {
    await loginAs(page, 'admin');
    const drawer = await openDrawer(page);
    await switchToPortalUsersTab(page, drawer);

    await page.locator('button:has-text("Invite portal user")').click();
    await page.waitForTimeout(1500);

    // Use an email that already exists from seeder
    await page.fill('#portalInviteName', 'Duplicate User');
    await page.fill('#portalInviteEmail', 'newsdesk@thedailystar.net');

    await page.locator('#portalInviteConfirm').click();
    await page.waitForTimeout(1500);

    // Validation error should appear
    const modal = page.locator('#portalInviteOverlay');
    const error = modal.locator('.text-xs.text-crimson, [class*="error"], .text-red-500').first();
    await expect(error).toBeVisible({ timeout: 5000 });
  });

  // 7
  test('Invite with empty name shows validation error', async ({ page }) => {
    await loginAs(page, 'admin');
    const drawer = await openDrawer(page);
    await switchToPortalUsersTab(page, drawer);

    await page.locator('button:has-text("Invite portal user")').click();
    await page.waitForTimeout(1500);

    await page.fill('#portalInviteName', '');
    await page.fill('#portalInviteEmail', `e2e-empty-${Date.now()}@test.com`);

    await page.locator('#portalInviteConfirm').click();
    await page.waitForTimeout(1500);

    const modal = page.locator('#portalInviteOverlay');
    const error = modal.locator('.text-xs.text-crimson, [class*="error"], .text-red-500').first();
    await expect(error).toBeVisible({ timeout: 5000 });
  });

  // 8
  test('Deactivate user changes status badge and shows Reactivate button', async ({ page }) => {
    await loginAs(page, 'admin');
    const drawer = await openDrawer(page);
    await switchToPortalUsersTab(page, drawer);

    const body = drawer.locator('#drBody');

    // Find an active user's Deactivate button
    const deactivateBtn = body.locator('button:has-text("Deactivate")').first();
    await deactivateBtn.click();
    await page.waitForTimeout(2000);

    // Status badge should show Deactivated
    await expect(body.locator('span:has-text("Deactivated")').first()).toBeVisible({ timeout: 5000 });

    // Reactivate button should appear
    await expect(body.locator('button:has-text("Reactivate")').first()).toBeVisible();
  });

  // 9
  test('Reactivate user changes status back to active', async ({ page }) => {
    await loginAs(page, 'admin');
    const drawer = await openDrawer(page);
    await switchToPortalUsersTab(page, drawer);

    const body = drawer.locator('#drBody');

    // First deactivate a user
    const deactivateBtn = body.locator('button:has-text("Deactivate")').first();
    await deactivateBtn.click();
    await page.waitForTimeout(2000);

    // Now reactivate
    const reactivateBtn = body.locator('button:has-text("Reactivate")').first();
    await reactivateBtn.click();
    await page.waitForTimeout(2000);

    // Status badge should show Active
    await expect(body.locator('span:has-text("Active")').first()).toBeVisible({ timeout: 5000 });
  });

  // 10
  test('Resend invite shows toast confirmation', async ({ page }) => {
    await loginAs(page, 'admin');
    const drawer = await openDrawer(page);
    await switchToPortalUsersTab(page, drawer);

    // First invite a user to have an "invited" user to resend to
    await page.locator('button:has-text("Invite portal user")').click();
    await page.waitForTimeout(1500);

    const uniqueEmail = `e2e-resend-${Date.now()}@test.com`;
    await page.fill('#portalInviteName', 'Resend Test User');
    await page.fill('#portalInviteEmail', uniqueEmail);
    // Select first available role
    const roleSelect = page.locator('#portalInviteRole');
    const options = await roleSelect.locator('option').all();
    if (options.length > 1) {
      const value = await options[1].getAttribute('value');
      if (value) await roleSelect.selectOption(value);
    }
    await page.locator('#portalInviteConfirm').click();
    await page.waitForTimeout(2000);

    // Close modal if still open
    const modal = page.locator('#portalInviteOverlay');
    if (await modal.evaluate((el) => el.classList.contains('open')).catch(() => false)) {
      await modal.locator('.mo-close').click();
      await page.waitForTimeout(500);
    }

    // Find Resend invite button for the invited user
    const body = drawer.locator('#drBody');
    const resendBtn = body.locator('button:has-text("Resend invite")').first();
    await expect(resendBtn).toBeVisible({ timeout: 5000 });
    await resendBtn.click();
    await page.waitForTimeout(1500);

    // Toast should appear
    const toast = page.locator('#toastWrap, [class*="toast"]').first();
    await expect(toast).toBeVisible({ timeout: 5000 });
  });

  // 11
  test('Change user role via dropdown shows toast', async ({ page }) => {
    await loginAs(page, 'admin');
    const drawer = await openDrawer(page);
    await switchToPortalUsersTab(page, drawer);

    const body = drawer.locator('#drBody');

    // Find the first role dropdown for an active user
    const roleDropdown = body.locator('select:has(option:has-text("Change role"))').first();
    const isVisible = await roleDropdown.isVisible().catch(() => false);

    if (!isVisible) {
      // No active user with role dropdown — skip gracefully
      test.skip();
      return;
    }

    // Select a different role (second option after "Change role…")
    const options = roleDropdown.locator('option:not([value=""])');
    const optionCount = await options.count();
    if (optionCount < 2) {
      test.skip();
      return;
    }

    // Pick the second role option
    const secondOptionValue = await options.nth(1).getAttribute('value');
    if (secondOptionValue) {
      await roleDropdown.selectOption(secondOptionValue);
    }

    await page.waitForTimeout(2000);

    // Toast should appear
    const toast = page.locator('#toastWrap, [class*="toast"]').first();
    await expect(toast).toBeVisible({ timeout: 5000 });
  });

  // 12
  test('Modal Cancel closes invite modal', async ({ page }) => {
    await loginAs(page, 'admin');
    const drawer = await openDrawer(page);
    await switchToPortalUsersTab(page, drawer);

    await page.locator('button:has-text("Invite portal user")').click();
    await page.waitForTimeout(1500);

    const modal = page.locator('#portalInviteOverlay');
    await expect(modal).toHaveClass(/open/, { timeout: 5000 });

    // Click Cancel button
    await modal.locator('button:has-text("Cancel")').click();
    await page.waitForTimeout(1000);

    await expect(modal).not.toHaveClass(/open/);
  });
});
