import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { loginAs } from './helpers/auth';

test.describe('Distribution Log & Retries (M11-DELIV)', () => {
  test.beforeEach(async () => {
    try {
      execSync('php tests/e2e/helpers/seed-data.php deliveries', { stdio: 'ignore' });
    } catch (_) {}
  });

  test('Page renders stats, table headers, and delivery records', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/distribution');
    await page.waitForLoadState('networkidle');

    // Heading
    await expect(page.locator('h1')).toHaveText('Distribution log');

    // Stat Cards
    const statsGrid = page.locator('.grid.grid-cols-3');
    await expect(statsGrid.getByText('Total deliveries')).toBeVisible();
    await expect(statsGrid.getByText('Delivered', { exact: true })).toBeVisible();
    await expect(statsGrid.getByText('Failed', { exact: true })).toBeVisible();

    // Table Column Headers
    const headers = ['Deliverable', 'Client', 'Channel', 'Status', 'Attempts', 'Action'];
    for (const h of headers) {
      await expect(page.locator('span', { hasText: h }).first()).toBeVisible();
    }

    // Records
    await expect(page.locator('text=e2e-failed-d')).toBeVisible();
    await expect(page.locator('text=e2e-delivere')).toBeVisible();
  });

  test('Status dropdown filters deliveries by status', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/distribution');
    await page.waitForLoadState('networkidle');

    const statusSelect = page.locator('select[wire\\:model\\.live="status"]');
    await expect(statusSelect).toBeVisible();

    // Filter by failed
    await statusSelect.selectOption('failed');
    await expect(page.locator('text=e2e-failed-d')).toBeVisible();
    await expect(page.locator('text=e2e-delivere')).not.toBeVisible();

    // Filter by delivered
    await statusSelect.selectOption('delivered');
    await expect(page.locator('text=e2e-delivere')).toBeVisible();
    await expect(page.locator('text=e2e-failed-d')).not.toBeVisible();
  });

  test('Clicking Retry on failed delivery dispatches toast and queues delivery', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/distribution');
    await page.waitForLoadState('networkidle');

    // Filter to failed
    const statusSelect = page.locator('select[wire\\:model\\.live="status"]');
    await statusSelect.selectOption('failed');

    // Find Retry button
    const retryBtn = page.locator('button:has-text("Retry")').first();
    await expect(retryBtn).toBeVisible();
    await retryBtn.click();

    // Verify toast "Queued for retry"
    await expect(page.locator('#toastWrap .toast, .toast').filter({ hasText: 'Queued for retry' })).toBeVisible({ timeout: 5000 });
  });
});
