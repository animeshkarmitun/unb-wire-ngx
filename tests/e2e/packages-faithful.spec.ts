import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('Packages & Add-ons Manager Faithful (M8-PACK-001)', () => {
  test.beforeAll(async () => {
    execSync('php artisan db:seed --class=PackageSeeder && php artisan db:seed --class=ClientSeeder', { stdio: 'ignore' });
  });

  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 }).catch(() => {});
  });

  test('Page header, breadcrumb, 4-stat strip, package grid, and add-on table render correctly', async ({ page }) => {
    await page.goto('/admin/packages');

    // Breadcrumb & Heading
    await expect(page.locator('.breadcrumb')).toContainText('Packages & add-ons');
    await expect(page.getByRole('heading', { name: 'Packages & add-ons' })).toBeVisible({ timeout: 5000 });

    // Topbar action buttons
    await expect(page.getByRole('button', { name: 'New add-on' })).toBeVisible();
    await expect(page.locator('.topbar-actions').getByRole('button', { name: 'New package', exact: true })).toBeVisible();

    // 4-Stat Strip
    await expect(page.locator('#stPkgs')).toHaveText('3');
    await expect(page.locator('#stAddons')).toHaveText('2');
    await expect(page.locator('#stClients')).toHaveText('7');
    await expect(page.locator('#stMrr')).toContainText('৳');

    // Subscription packages section
    await expect(page.locator('.sec-title').first()).toHaveText('Subscription packages');
    await expect(page.locator('#pkgGrid')).toContainText('Premium Wire + Media');
    await expect(page.locator('#pkgGrid')).toContainText('Standard Wire');
    await expect(page.locator('#pkgGrid')).toContainText('Basic Headlines');
    await expect(page.locator('#pkgGrid')).toContainText('District Wire');
    await expect(page.locator('#pkgNewCard')).toBeVisible();

    // Add-on section
    await expect(page.locator('.sec-title').nth(1)).toHaveText('Add-ons');
    await expect(page.locator('#addonList')).toContainText('AP World pack');
    await expect(page.locator('#addonList')).toContainText('Bangla service');
    await expect(page.locator('#addonList')).toContainText('Sports data feed');
  });

  test('Package Editor Modal opens, live preview updates, and creates new package', async ({ page }) => {
    await page.goto('/admin/packages');

    const pkgName = `Election 2026 Wire ${Date.now()}`;

    // Open modal
    await page.click('#pkgNewCard');
    await expect(page.locator('#pkgOverlay')).toHaveClass(/open/);
    await expect(page.locator('#pkgMoTitle')).toHaveText('New package');

    // Fill inputs
    await page.fill('#pName', pkgName);
    await page.fill('#pPrice', '55000');
    await page.fill('#pDesc', 'Special live coverage for election year');

    // Select color dot g3
    await page.locator('#pColors .color-dot.g3').click();

    // Verify live client view preview
    await expect(page.locator('#pvName')).toHaveText(pkgName);
    await expect(page.locator('#pvPrice')).toHaveText('৳55,000');
    await expect(page.locator('#pvTop')).toHaveClass(/g3/);

    // Save & publish
    await page.click('#pkgSave');

    // Modal closes & card appears
    await expect(page.locator('#pkgOverlay')).not.toHaveClass(/open/);
    await expect(page.locator('#pkgGrid')).toContainText(pkgName);
  });

  test('Duplicate package creates draft copy', async ({ page }) => {
    await page.goto('/admin/packages');

    // Find Basic Headlines card and click Duplicate
    const basicCard = page.locator('.pkg-card', { hasText: 'Basic Headlines' }).first();
    await basicCard.getByRole('button', { name: 'Duplicate' }).click();

    // Verify duplicate copy card exists
    await expect(page.locator('#pkgGrid')).toContainText('Basic Headlines (copy)', { timeout: 8000 });
  });

  test('Add-on Editor Modal creates new add-on and status chip toggles live and draft', async ({ page }) => {
    await page.goto('/admin/packages');

    const aoName = `Video B-roll Feed ${Date.now()}`;

    // Open Add-on modal
    await page.getByRole('button', { name: 'New add-on' }).click();
    await expect(page.locator('#aoOverlay')).toHaveClass(/open/);
    await expect(page.locator('#aoMoTitle')).toHaveText('New add-on');

    // Fill form
    await page.fill('#aName', aoName);
    await page.fill('#aPrice', '25000');
    await page.fill('#aDesc', 'High definition broadcast news clips');

    // Save
    await page.click('#aoSave');
    await expect(page.locator('#aoOverlay')).not.toHaveClass(/open/);

    // Verify in table
    const newAoRow = page.locator('#addonList .ao-row', { hasText: aoName });
    await expect(newAoRow).toBeVisible({ timeout: 5000 });
    await expect(newAoRow.locator('.ao-status')).toHaveText('draft');

    // Click status chip to toggle to live
    await newAoRow.locator('.ao-status').click();
    await expect(newAoRow.locator('.ao-status')).toHaveText('live', { timeout: 5000 });
  });

  test('Archive modal opens with client reassignment options and archives package', async ({ page }) => {
    await page.goto('/admin/packages');

    // Find Standard Wire card and click Archive
    const stdCard = page.locator('.pkg-card', { hasText: 'Standard Wire' }).first();
    await stdCard.getByRole('button', { name: 'Archive' }).click();

    // Verify Archive modal opened with client warning
    await expect(page.locator('#archOverlay')).toHaveClass(/open/);
    await expect(page.locator('#archTitle')).toContainText('Standard Wire');
    await expect(page.locator('#archBody .warn-box')).toBeVisible();

    // Reassign dropdown has options
    await expect(page.locator('#archReassign')).toBeVisible();

    // Confirm archive
    await page.click('#archConfirm');
    await expect(page.locator('#archOverlay')).not.toHaveClass(/open/);

    // Standard Wire should now have archived status or Restore button
    await expect(stdCard).toHaveClass(/archived/);
    await expect(stdCard.getByRole('button', { name: 'Restore' })).toBeVisible();
  });
});
