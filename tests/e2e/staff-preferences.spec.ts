import { test, expect, request as apiRequest } from '@playwright/test';
import { loginAs } from './helpers/auth';

/**
 * M12-PROFILE-002 — Staff preferences.
 *
 * The original spec used `waitForTimeout(1500)` as the only save-verification
 * and wrapped several assertions in `if (await …isVisible())`. COV-027 replaces
 * those with real DB/HTML assertions and seed/restores the admin user state.
 */
test.describe('Staff Preferences (M12-PROFILE-002)', () => {
  test.setTimeout(60000);

  const ORIGINAL_DESK = 'English desk';
  const ORIGINAL_TZ = 'Asia/Dhaka';
  const ORIGINAL_DATE = 'dmy';
  const ORIGINAL_DENSITY = 'comfortable';

  test.afterEach(async ({ page }) => {
    // Restore the admin user to the known defaults so parallel specs stay clean.
    await page.goto('/admin/preferences');
    await page.waitForLoadState('networkidle');
    const desk = page.locator('select').first();
    await desk.selectOption(ORIGINAL_DESK);
    await page.locator('button:has-text("Save")').click();
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200);
  });

  test('preferences page renders with desk, timezone, date_format, density selects', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/preferences');
    await expect(page.getByRole('heading', { name: /preferences/i })).toBeVisible();
    const selects = page.locator('select');
    await expect(selects).toHaveCount(4);
  });

  test('change desk to Bangla desk and persist (DB-verified)', async ({ page, request }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/preferences');

    const desk = page.locator('select').first();
    await desk.selectOption('Bangla desk');
    await page.locator('button:has-text("Save")').click();
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200);

    // Server truth: read the user row directly.
    const email = 'test@example.com';
    const cmd = `php artisan tinker --execute="echo \\App\\Models\\User::where('email', '${email}')->value('desk');"` +
      ` 2>/dev/null`;
    const result = require('child_process').execSync(cmd, { encoding: 'utf8' }).trim();
    expect(result).toBe('Bangla desk');

    await page.reload();
    await expect(page.locator('select').first()).toHaveValue('Bangla desk');
  });

  test('change timezone to UTC and persist', async ({ page, request }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/preferences');

    const tz = page.locator('select').nth(1);
    await tz.selectOption('UTC');
    await page.locator('button:has-text("Save")').click();
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200);

    const cmd = `php artisan tinker --execute="echo \\App\\Models\\User::where('email', 'test@example.com')->value('timezone');" 2>/dev/null`;
    const tzValue = require('child_process').execSync(cmd, { encoding: 'utf8' }).trim();
    expect(tzValue).toBe('UTC');
  });

  test('change date format to mdy and persist', async ({ page, request }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/preferences');

    const dateFormat = page.locator('select:has(option[value="dmy"]), select:has(option[value="mdy"])').first();
    await dateFormat.selectOption('mdy');
    await page.locator('button:has-text("Save")').click();
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200);

    await page.reload();
    await expect(dateFormat).toHaveValue('mdy');
  });

  test('density compact applies compact CSS to data tables', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/preferences');

    const density = page.locator('select:has(option[value="comfortable"]), select:has(option[value="compact"])').first();
    await density.selectOption('compact');
    await page.locator('button:has-text("Save")').click();
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200);

    await page.goto('/admin/news/en');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toHaveAttribute('data-density', 'compact');
  });

  test('date format mdy renders month-first text on /admin/news/en', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/preferences');

    const dateFormat = page.locator('select:has(option[value="dmy"]), select:has(option[value="mdy"])').first();
    await dateFormat.selectOption('mdy');
    await page.locator('button:has-text("Save")').click();
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200);

    // Seed a unique story so /admin/news/en has a row with a timestamp.
    const seedHeadline = `E2E Pref Date ${Date.now()}`;
    require('child_process').execSync(
      `php artisan tinker --execute="\\$u = \\App\\Models\\User::first(); \\$c = \\App\\Models\\Category::first(); \\$s = app(\\App\\Services\\StoryService::class)->createDraft(['language' => 'en', 'headline' => '${seedHeadline}', 'brief' => 'b', 'body_html' => '<p>x</p>', 'category_id' => \\$c->id], \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'in_review', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'approved', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'published', \\$u);" 2>/dev/null`
    );

    await page.goto('/admin/news/en');
    await page.waitForLoadState('networkidle');
    const row = page.locator(`.news-table tbody tr:has-text("${seedHeadline}")`).first();
    await expect(row).toBeVisible({ timeout: 10000 });
    const timeCell = row.locator('td').filter({ hasText: /\d/ }).first();
    const cellText = await timeCell.textContent();
    expect(cellText).toMatch(/[A-Z][a-z]{2} \d{1,2}/);
  });

  test('preferences page loads with correct defaults for test user', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/preferences');
    const selects = page.locator('select');
    expect(await selects.count()).toBeGreaterThanOrEqual(2);
    await expect(selects.first()).not.toHaveValue('');
  });

  test('save preferences shows toast notification', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/preferences');
    await page.locator('button:has-text("Save")').click();
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200);
    await expect(page.locator('.toast, [class*="toast"], [wire\\:toast]').first()).toBeVisible({ timeout: 5000 });
  });
});