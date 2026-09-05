import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('Roles & Access Manager Faithful (M8-ROLE-001)', () => {
  test.beforeAll(async () => {
    execSync('php artisan db:seed --class=RoleSeeder && php artisan db:seed --class=UserSeeder', { stdio: 'ignore' });
  });

  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 }).catch(() => {});
  });

  test('Page header, breadcrumb, 4-stat strip, tabs, and canonical roles render correctly', async ({ page }) => {
    await page.goto('/admin/roles');

    // Breadcrumb & Heading
    await expect(page.locator('.breadcrumb')).toContainText('Roles & access');
    await expect(page.getByRole('heading', { name: 'Roles & access' })).toBeVisible({ timeout: 5000 });

    // Topbar action buttons
    await expect(page.locator('.topbar-actions').getByRole('button', { name: 'Invite member' })).toBeVisible();
    await expect(page.locator('.topbar-actions').getByRole('button', { name: 'New role' })).toBeVisible();

    // 4-Stat Strip
    await expect(page.locator('#stRoles')).toHaveText('8');
    await expect(page.locator('#stPeople')).toBeVisible();
    await expect(page.locator('#stCustom')).toHaveText('6');
    await expect(page.locator('#stInvited')).toHaveText('2');

    // Tab buttons & initial panel
    const tabs = page.locator('.pg-tabs .pg-tab');
    await expect(tabs).toHaveCount(3);
    await expect(tabs.nth(0)).toContainText('Roles');
    await expect(tabs.nth(1)).toContainText('People');
    await expect(tabs.nth(2)).toContainText('Activity');

    // Roles Panel
    await expect(page.locator('#roleGrid')).toBeVisible();
    await expect(page.locator('#roleGrid')).toContainText('Admin');
    await expect(page.locator('#roleGrid')).toContainText('Editor');
    await expect(page.locator('#roleGrid')).toContainText('Strategist');
    await expect(page.locator('#roleGrid')).toContainText('Admin Report');
    await expect(page.locator('#roleGrid')).toContainText('Business Team');
    await expect(page.locator('#roleGrid')).toContainText('Client Bangla (Without AP)');
    await expect(page.locator('#roleGrid')).toContainText('Uploader-Bangla');
    await expect(page.locator('#roleGrid')).toContainText('Uploader-English');
    await expect(page.locator('#roleNewCard')).toBeVisible();

    // Info banner
    await expect(page.locator('.info-banner')).toContainText('Client roles');
  });

  test('Tab navigation switches between Roles, People, and Activity panels', async ({ page }) => {
    await page.goto('/admin/roles');

    // Switch to People tab
    await page.locator('.pg-tab', { hasText: 'People' }).click();
    await expect(page.locator('#ppList')).toBeVisible();
    await expect(page.locator('.pp-row.head')).toContainText('Member');
    await expect(page.locator('#ppList')).toContainText('Shohel Ahmed');
    await expect(page.locator('#ppList')).toContainText('Ruma Islam');

    // Switch to Activity tab
    await page.locator('.pg-tab', { hasText: 'Activity' }).click();
    await expect(page.locator('#auList')).toBeVisible();

    // Switch back to Roles tab
    await page.locator('.pg-tab', { hasText: 'Roles' }).click();
    await expect(page.locator('#roleGrid')).toBeVisible();
  });

  test('Role Editor Drawer opens, applies quick presets, and saves', async ({ page }) => {
    await page.goto('/admin/roles');

    // Find the Editor card and click Edit permissions
    const editorCard = page.locator('.role-card', { hasText: 'Editor' }).first();
    await editorCard.getByRole('button', { name: 'Edit permissions' }).click();

    // Drawer opens
    const drawer = page.locator('aside.drawer');
    await expect(drawer).toHaveClass(/open/);
    await expect(drawer.locator('.dr-name')).toHaveText('Editor');

    // Apply "View only" preset
    await drawer.getByRole('button', { name: 'View only' }).click();

    // Check count on English News module
    const enCount = drawer.locator('.mx-mod', { hasText: 'English News' }).locator('.mx-mod-count');
    await expect(enCount).toHaveText('1/5');

    // Save role
    await drawer.getByRole('button', { name: 'Save role' }).click();
    await expect(drawer).not.toHaveClass(/open/);

    // Verify toast
    await expect(page.locator('#toastWrap div, .toast')).toBeVisible({ timeout: 5000 });
  });

  test('System role (Admin) is locked and read-only in drawer', async ({ page }) => {
    await page.goto('/admin/roles');

    const adminCard = page.locator('.role-card', { hasText: 'Admin' }).first();
    await expect(adminCard.locator('.rc-lock')).toHaveText('System');
    await expect(adminCard.getByRole('button', { name: 'Delete' })).not.toBeVisible();

    // Click "View permissions"
    await adminCard.getByRole('button', { name: 'View permissions' }).click();

    const drawer = page.locator('aside.drawer');
    await expect(drawer).toHaveClass(/open/);
    await expect(drawer.locator('.dr-name')).toHaveText('Admin');
    await expect(drawer.locator('.mx-locked')).toContainText('System role.');
    await expect(drawer.getByRole('button', { name: 'Save role' })).not.toBeVisible();

    // Close drawer
    await drawer.locator('.dr-close').click();
    await expect(drawer).not.toHaveClass(/open/);
  });

  test('Create a custom role via modal and duplicate role', async ({ page }) => {
    await page.goto('/admin/roles');

    const roleName = `Night Cycle Lead ${Date.now()}`;

    // Click "New role" topbar button
    await page.locator('.topbar-actions').getByRole('button', { name: 'New role' }).click();
    const modal = page.locator('.modal-overlay.open .modal');
    await expect(modal.locator('.mo-title')).toHaveText('Create a role');

    // Fill new role
    await modal.locator('input[type="text"]').fill(roleName);
    await modal.locator('textarea').fill('Coordinates overnight dispatches');
    await modal.getByRole('button', { name: 'Create role' }).click();

    // After creation, drawer opens automatically to fine-tune
    const drawer = page.locator('aside.drawer');
    await expect(drawer).toHaveClass(/open/);
    await expect(drawer.locator('.dr-name')).toHaveText(roleName);
    await drawer.getByRole('button', { name: 'Discard' }).click();

    // Verify newly created role appears in grid
    await expect(page.locator('#roleGrid')).toContainText(roleName);

    // Test Duplication on Strategist
    const stratCard = page.locator('.role-card', { hasText: 'Strategist' }).first();
    await stratCard.getByRole('button', { name: 'Duplicate' }).click();
    await expect(page.locator('#roleGrid')).toContainText('Strategist (copy)');
  });

  test('Delete modal warns and blocks deletion when role has members', async ({ page }) => {
    await page.goto('/admin/roles');

    const editorCard = page.locator('.role-card', { hasText: 'Editor' }).first();
    await editorCard.getByRole('button', { name: 'Delete' }).click();

    const modal = page.locator('.modal-overlay.open .modal');
    await expect(modal.locator('.mo-title')).toContainText('Delete "Editor"?');
    await expect(modal.locator('.warn-box')).toContainText('still have this role');
    await expect(modal.getByRole('button', { name: 'Delete role' })).toBeDisabled();

    await modal.getByRole('button', { name: 'Cancel' }).click();
    await expect(page.locator('.modal-overlay.open')).not.toBeVisible();
  });

  test('People tab supports live role assignment, deactivation, and reactivation', async ({ page }) => {
    await page.goto('/admin/roles');

    // Switch to People tab
    await page.locator('.pg-tab', { hasText: 'People' }).click();
    await expect(page.locator('#ppList')).toBeVisible();

    // Find Shohel Ahmed's row
    const shohelRow = page.locator('.pp-row', { hasText: 'Shohel Ahmed' }).first();
    await expect(shohelRow).toBeVisible();

    // Deactivate
    await shohelRow.getByRole('button', { name: 'Deactivate' }).click();
    await expect(shohelRow.locator('.pp-status')).toContainText('deactivated');
    await expect(shohelRow.getByRole('button', { name: 'Reactivate' })).toBeVisible();

    // Reactivate
    await shohelRow.getByRole('button', { name: 'Reactivate' }).click();
    await expect(shohelRow.locator('.pp-status')).toContainText('active');
    await expect(shohelRow.getByRole('button', { name: 'Deactivate' })).toBeVisible();
  });
});
