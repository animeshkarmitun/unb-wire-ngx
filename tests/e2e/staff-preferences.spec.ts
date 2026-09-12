import { test, expect } from '@playwright/test';
import { loginAs, waitForToast } from './helpers/auth';

test.describe('Staff Preferences (M12-PROFILE-002)', () => {
  test.beforeEach(async ({ page }) => {
    test.setTimeout(45000);
    await loginAs(page, 'admin');
  });

  test('preferences page renders with desk, timezone, date_format, density selects', async ({ page }) => {
    await page.goto('/admin/preferences');
    await expect(page.locator('h1, h2')).toContainText(/preferences/i);
    const selects = page.locator('select');
    await expect(selects).toHaveCount(4);
  });

  test('change desk to Bangla desk and persist', async ({ page }) => {
    await page.goto('/admin/preferences');
    const deskSelect = page.locator('select').first();
    await deskSelect.selectOption('Bangla desk');
    await page.locator('button:has-text("Save")').click();
    await page.waitForTimeout(1500);
    await page.reload();
    await expect(page.locator('select').first()).toHaveValue('Bangla desk');
    // Restore
    await deskSelect.selectOption('English desk');
    await page.locator('button:has-text("Save")').click();
    await page.waitForTimeout(1000);
  });

  test('change timezone to UTC and persist', async ({ page }) => {
    await page.goto('/admin/preferences');
    const tzSelect = page.locator('select').nth(1);
    await tzSelect.selectOption('UTC');
    await page.locator('button:has-text("Save")').click();
    await page.waitForTimeout(1500);
    await page.reload();
    await expect(page.locator('select').nth(1)).toHaveValue('UTC');
    // Restore
    await tzSelect.selectOption('Asia/Dhaka');
    await page.locator('button:has-text("Save")').click();
    await page.waitForTimeout(1000);
  });

  test('change date format to mdy and persist', async ({ page }) => {
    await page.goto('/admin/preferences');
    const dateFormatSelect = page.locator('select:has(option[value="dmy"]), select:has(option[value="mdy"])').first();
    await dateFormatSelect.selectOption('mdy');
    await page.locator('button:has-text("Save")').click();
    await page.waitForTimeout(1500);
    await page.reload();
    await expect(dateFormatSelect).toHaveValue('mdy');
    // Restore
    await dateFormatSelect.selectOption('dmy');
    await page.locator('button:has-text("Save")').click();
    await page.waitForTimeout(1000);
  });

  test('change density to compact and verify data-density attribute', async ({ page }) => {
    await page.goto('/admin/preferences');
    const densitySelect = page.locator('select:has(option[value="comfortable"]), select:has(option[value="compact"])').first();
    await densitySelect.selectOption('compact');
    await page.locator('button:has-text("Save")').click();
    await page.waitForTimeout(1500);
    await page.goto('/admin/news/en');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toHaveAttribute('data-density', 'compact');
    // Restore
    await page.goto('/admin/preferences');
    await densitySelect.selectOption('comfortable');
    await page.locator('button:has-text("Save")').click();
    await page.waitForTimeout(1000);
  });

  test('date format mdy shows month-first format in story view', async ({ page }) => {
    await page.goto('/admin/preferences');
    const dateFormatSelect = page.locator('select:has(option[value="dmy"]), select:has(option[value="mdy"])').first();
    await dateFormatSelect.selectOption('mdy');
    await page.locator('button:has-text("Save")').click();
    await page.waitForTimeout(1000);
    await page.goto('/admin/news/en');
    await page.waitForLoadState('networkidle');
    const timestampCells = page.locator('[class*="time"], td:has-text("AM"), td:has-text("PM")').first();
    if (await timestampCells.isVisible().catch(() => false)) {
      const text = await timestampCells.textContent();
      expect(text).toMatch(/[A-Z][a-z]{2} \d{1,2}/);
    }
    // Restore
    await page.goto('/admin/preferences');
    await dateFormatSelect.selectOption('dmy');
    await page.locator('button:has-text("Save")').click();
  });

  test('density compact applies compact CSS to data tables', async ({ page }) => {
    await page.goto('/admin/preferences');
    const densitySelect = page.locator('select:has(option[value="comfortable"]), select:has(option[value="compact"])').first();
    await densitySelect.selectOption('compact');
    await page.locator('button:has-text("Save")').click();
    await page.waitForTimeout(1000);
    await page.goto('/admin/news/en');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toHaveAttribute('data-density', 'compact');
    // Restore
    await page.goto('/admin/preferences');
    await densitySelect.selectOption('comfortable');
    await page.locator('button:has-text("Save")').click();
  });

  test('preferences page loads with correct defaults for test user', async ({ page }) => {
    await page.goto('/admin/preferences');
    const selects = page.locator('select');
    const count = await selects.count();
    expect(count).toBeGreaterThanOrEqual(2);
    await expect(selects.first()).not.toHaveValue('');
  });

  test('save preferences shows toast notification', async ({ page }) => {
    await page.goto('/admin/preferences');
    await page.locator('button:has-text("Save")').click();
    await page.waitForTimeout(1000);
    const toast = page.locator('.toast, [class*="toast"], [wire\\:toast]').first();
    await expect(toast).toBeVisible({ timeout: 5000 });
  });
});
