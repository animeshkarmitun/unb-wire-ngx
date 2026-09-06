import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('AI Settings Manager Faithful (M8-AI-001)', () => {
  test.beforeAll(async () => {
    execSync('php artisan db:seed --class=RoleSeeder && php artisan db:seed --class=UserSeeder && php artisan db:seed --class=SettingSeeder', { stdio: 'ignore' });
  });

  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 }).catch(() => {});
  });

  test('Page header, master status banner, cards, usage table, and save bar render correctly', async ({ page }) => {
    await page.goto('/admin/ai-settings');

    // Breadcrumb & Heading
    await expect(page.locator('.breadcrumb')).toContainText('AI settings');
    await expect(page.getByRole('heading', { name: 'AI settings' })).toBeVisible({ timeout: 5000 });

    // Master status banner
    const banner = page.locator('#statusBanner');
    await expect(banner).toBeVisible();
    await expect(banner.locator('.pulse')).toBeVisible();
    await expect(page.locator('#statusTitle')).toHaveText('AI pre-edit is active');
    await expect(page.locator('#statusAuto')).toHaveText('OFF');

    // Desk switches
    await expect(page.locator('#swEn')).toBeChecked();
    await expect(page.locator('#swBn')).toBeChecked();
    await expect(page.locator('#swPhotos')).not.toBeChecked();

    // Auto-publish section
    await expect(page.locator('#autoOffBox')).toBeVisible();
    await expect(page.locator('#autoOnBox')).toBeHidden();
    await expect(page.locator('#swAuto')).not.toBeChecked();

    // Category chips
    await expect(page.locator('#autoCats button[data-cat="Weather"]')).toHaveClass(/on/);
    await expect(page.locator('#autoCats button[data-cat="Sports results"]')).toHaveClass(/on/);
    await expect(page.locator('#autoCats button[data-cat="Bangladesh"]')).not.toHaveClass(/on/);

    // Token usage & budget
    await expect(page.locator('#usageFill')).toBeVisible();
    await expect(page.locator('#usageNow')).toContainText('312,400');
    await expect(page.locator('#usageCapLabel')).toContainText('500,000');
    await expect(page.locator('#usageCost')).toContainText('৳');
    await expect(page.locator('.desk-table')).toContainText('English News');
    await expect(page.locator('.desk-table')).toContainText('Bangla News');
    await expect(page.locator('.desk-table')).toContainText('UNB Photos');
    await expect(page.locator('#capInput')).toHaveValue('500000');

    // House style prompt
    await expect(page.locator('#stylePrompt')).toHaveValue(/You are a UNB wire copy editor/);

    // Emergency kill switch
    await expect(page.locator('#killBtn')).toHaveText('Disable all AI features now');

    // Save bar
    await expect(page.locator('#resetBtn')).toBeVisible();
    await expect(page.locator('#saveBtn')).toBeVisible();
  });

  test('Auto-publish modal safeguard flow and category chip toggling', async ({ page }) => {
    await page.goto('/admin/ai-settings');

    // Clicking switch opens confirmation modal
    await page.locator('#swAuto').click();
    const modal = page.locator('#autoModal');
    await expect(modal).toBeVisible();
    await expect(modal).toContainText('Enable AI auto-publish?');

    // Cancel keeping it off
    await page.locator('#autoCancel').click();
    await expect(modal).toBeHidden();
    await expect(page.locator('#swAuto')).not.toBeChecked();
    await expect(page.locator('#autoOffBox')).toBeVisible();

    // Open and confirm
    await page.locator('#swAuto').click();
    await expect(modal).toBeVisible();
    await page.locator('#autoConfirm').click();
    await expect(modal).toBeHidden();
    await expect(page.locator('#swAuto')).toBeChecked();
    await expect(page.locator('#autoOnBox')).toBeVisible();
    await expect(page.locator('#statusAuto')).toHaveText('ON');

    // Toggle category chips
    const bdChip = page.locator('#autoCats button[data-cat="Bangladesh"]');
    await expect(bdChip).not.toHaveClass(/on/);
    await bdChip.click();
    await expect(bdChip).toHaveClass(/on/);

    await bdChip.click();
    await expect(bdChip).not.toHaveClass(/on/);

    // Switch auto-publish back off directly
    await page.locator('#swAuto').click();
    await expect(page.locator('#swAuto')).not.toBeChecked();
    await expect(page.locator('#autoOffBox')).toBeVisible();
    await expect(page.locator('#statusAuto')).toHaveText('OFF');
  });

  test('Emergency kill switch immediately updates UI banner and state', async ({ page }) => {
    await page.goto('/admin/ai-settings');

    const banner = page.locator('#statusBanner');
    const killBtn = page.locator('#killBtn');

    // Trip kill switch
    await killBtn.click();
    await expect(banner).toHaveClass(/off/);
    await expect(page.locator('#statusTitle')).toHaveText('All AI features are disabled (kill switch)');
    await expect(killBtn).toHaveText('Re-enable AI features');
    await expect(killBtn).toHaveClass(/restore/);

    // Untrip kill switch
    await killBtn.click();
    await expect(banner).not.toHaveClass(/off/);
    await expect(page.locator('#statusTitle')).toHaveText('AI pre-edit is active');
    await expect(killBtn).toHaveText('Disable all AI features now');
    await expect(killBtn).not.toHaveClass(/restore/);
  });

  test('Saving modified settings persists to database and reflects across reloads', async ({ page }) => {
    await page.goto('/admin/ai-settings');

    // Turn on UNB photos toggle
    await page.locator('#swPhotos').check();
    await expect(page.locator('#swPhotos')).toBeChecked();

    // Change monthly token cap
    await page.locator('#capInput').fill('750000');
    await page.locator('#capInput').blur();

    // Click Save settings
    await page.locator('#saveBtn').click();
    await expect(page.locator('.toast').last()).toContainText('saved');

    // Reload page and assert persistence
    await page.reload();
    await expect(page.locator('#swPhotos')).toBeChecked();
    await expect(page.locator('#capInput')).toHaveValue('750000');
    await expect(page.locator('#usageCapLabel')).toContainText('750,000');

    // Reset to defaults
    await page.locator('#resetBtn').click();
    await expect(page.locator('#swPhotos')).not.toBeChecked();
    await expect(page.locator('#capInput')).toHaveValue('500000');
    await page.locator('#saveBtn').click();
    await expect(page.locator('.toast').last()).toContainText('saved');
  });
});
