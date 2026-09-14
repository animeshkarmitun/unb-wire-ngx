import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { loginAs } from './helpers/auth';

test.describe('Global Audit Log Browser (M10-HIST)', () => {
  test.setTimeout(45000);

  test.beforeEach(async () => {
    try {
      execSync('php tests/e2e/helpers/seed-data.php audit', { stdio: 'ignore' });
    } catch (_) {}
  });

  test('RBAC: Non-authorized user cannot access /admin/audit', async ({ page }) => {
    await loginAs(page, 'biz');

    const res = await page.goto('/admin/audit');
    const status = res?.status() ?? 0;

    const isForbidden =
      status === 403 ||
      (await page
        .locator('body')
        .textContent()
        .then((t) => /forbidden|not authorized|access denied/i.test(t ?? ''))
        .catch(() => false));

    const isRedirected = !page.url().includes('/admin/audit');
    expect(isForbidden || isRedirected).toBeTruthy();
  });

  test('Admin user accesses /admin/audit and verifies table headers and records', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/audit');
    await page.waitForLoadState('networkidle');

    // Header & Subtitle
    await expect(page.locator('h1')).toHaveText('Audit Log');
    await expect(
      page.getByText('Append-only record of every sensitive action across the newsroom')
    ).toBeVisible();

    // Table Column Headers
    const headers = ['Time', 'Actor', 'Action', 'Entity', 'Diff', 'IP', 'Correlation'];
    for (const h of headers) {
      await expect(page.locator('table th', { hasText: h })).toBeVisible();
    }

    // Seeded audit records should be visible
    await expect(page.locator('table tbody')).toContainText('User #1');
    await expect(page.locator('table tbody')).toContainText('Story #42');
    await expect(page.locator('table tbody')).toContainText('role #2');
    await expect(page.locator('table tbody')).toContainText('127.0.0.1');
    await expect(page.locator('table tbody')).toContainText('11111111-222');
  });

  test('Filter controls filter audit records dynamically', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/audit');
    await page.waitForLoadState('networkidle');

    // 1. Filter by Entity Type: Role
    const typeSelect = page.locator('select[wire\\:model\\.live="filterEntityType"]');
    await typeSelect.selectOption('role');

    // Livewire updates table
    await expect(page.locator('table tbody')).toContainText('role #2');
    await expect(page.locator('table tbody')).not.toContainText('Story #42');

    // 2. Reset Entity Type filter
    await typeSelect.selectOption('');
    await expect(page.locator('table tbody')).toContainText('Story #42');

    // 3. Filter by Entity ID
    const entityIdInput = page.locator('input[wire\\:model\\.live\\.debounce\\.300ms="filterEntityId"]');
    await entityIdInput.fill('42');

    await expect(page.locator('table tbody')).toContainText('Story #42');
    await expect(page.locator('table tbody')).not.toContainText('role #2');
  });
});
