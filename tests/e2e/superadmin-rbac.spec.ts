import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { loginAs } from './helpers/auth';

test.describe('Superadmin RBAC & System Role Protection (M13-RBAC)', () => {
  test.beforeEach(async () => {
    try {
      execSync('php tests/e2e/helpers/seed-data.php superadmin', { stdio: 'ignore' });
    } catch (_) {}
  });

  test('Superadmin user sees Superadmin badge and management actions in People tab', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await page.goto('/admin/roles');
    await page.waitForLoadState('networkidle');

    // Switch to People tab
    await page.locator('.pg-tab', { hasText: 'People' }).click();
    await expect(page.locator('#ppList')).toBeVisible();

    // Verify Superadmin badge is rendered for superadmin users
    const superadminBadge = page.locator('#ppList span.rc-lock:has-text("Superadmin")').first();
    await expect(superadminBadge).toBeVisible();

    // Superadmin should see Grant/Revoke superadmin toggle actions on team members
    const superadminToggles = page.locator('#ppList button.pp-act:has-text("superadmin")');
    await expect(superadminToggles.first()).toBeVisible();
  });

  test('Superadmin can edit locked system roles', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await page.goto('/admin/roles');
    await page.waitForLoadState('networkidle');

    // Find the Admin card
    const adminCard = page.locator('.role-card', { hasText: 'Admin' }).first();
    await expect(adminCard).toBeVisible();

    // As Superadmin, button should say "Edit permissions"
    const editBtn = adminCard.locator('button.rc-btn', { hasText: 'Edit permissions' });
    await expect(editBtn).toBeVisible();
    await editBtn.click();

    // Drawer should open and allow saving
    const drawer = page.locator('aside.drawer');
    await expect(drawer).toHaveClass(/open/);
    await expect(drawer.locator('.dr-name')).toHaveText('Admin');

    const saveBtn = drawer.locator('button:has-text("Save role")');
    await expect(saveBtn).toBeVisible();

    // Close drawer
    await drawer.locator('button:has-text("Discard")').click();
    await expect(drawer).not.toHaveClass(/open/);
  });

  test('Non-superadmin cannot edit system roles or toggle superadmin access', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/roles');
    await page.waitForLoadState('networkidle');

    // On Roles tab, Admin card should only show "View permissions"
    const adminCard = page.locator('.role-card', { hasText: 'Admin' }).first();
    await expect(adminCard).toBeVisible();

    const viewBtn = adminCard.locator('button.rc-btn', { hasText: 'View permissions' });
    await expect(viewBtn).toBeVisible();
    await viewBtn.click();

    // Drawer opens in read-only mode for locked system role
    const drawer = page.locator('aside.drawer');
    await expect(drawer).toHaveClass(/open/);
    await expect(drawer.locator('button:has-text("Save role")')).not.toBeVisible();
    await drawer.locator('button:has-text("Discard")').click();

    // In People tab, Grant/Revoke superadmin buttons should NOT exist
    await page.locator('.pg-tab', { hasText: 'People' }).click();
    await expect(page.locator('#ppList')).toBeVisible();

    const superadminToggles = page.locator('#ppList button.pp-act:has-text("superadmin")');
    await expect(superadminToggles).toHaveCount(0);
  });
});
