import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { execSync } from 'child_process';

test.describe('AI Editorial Assistant, Token Quota & Guardrail Flow (M8-AI)', () => {
  test.beforeEach(async ({ page }) => {
    try {
      execSync('php tests/e2e/helpers/seed-data.php ai', { stdio: 'ignore' });
    } catch (_) {}
    await loginAs(page, 'admin');
  });

  test('Start with AI generation in Add News drafts headline, brief, category, and tags into suggestion drawer', async ({ page }) => {
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');

    const rawTextarea = page.locator('#aiRaw');
    await expect(rawTextarea).toBeVisible();
    await rawTextarea.fill('Padma bridge rail link freight service starts tomorrow with commercial container trains');

    const generateBtn = page.locator('#aiGenerateBtn');
    await expect(generateBtn).toBeVisible();
    await generateBtn.click();

    // AI Drawer opens with suggestion cards
    const drawer = page.locator('#aiDrawer');
    await expect(drawer).toHaveClass(/open/, { timeout: 10000 });
    await expect(page.locator('#aidCardHeadline')).toContainText('AI Generated headline');
    await expect(page.locator('#aidCardBrief')).toContainText('AI brief');
    await expect(page.locator('#aidCardCategory')).toContainText('Bangladesh');
    await expect(page.locator('#aidCardTags')).toContainText('#breaking');
  });

  test('Applying AI suggestions populates form fields, marks them with ai-touched styling, and human edit clears marker', async ({ page }) => {
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');

    await page.locator('#aiRaw').fill('Padma bridge rail link freight service starts tomorrow');
    await page.locator('#aiGenerateBtn').click();

    const drawer = page.locator('#aiDrawer');
    await expect(drawer).toHaveClass(/open/, { timeout: 10000 });

    // Apply Headline
    await page.locator('#aidApplyHeadline').click();
    await expect(page.locator('#headlineInput')).toHaveValue('AI Generated headline');
    const headlineWrap = page.locator('.field:has(#headlineInput)');
    await expect(headlineWrap).toHaveClass(/ai-touched/);

    // Apply Brief
    await page.locator('#aidApplyBrief').click();
    await expect(page.locator('#briefInput')).toHaveValue('AI brief');
    const briefWrap = page.locator('.field:has(#briefInput)');
    await expect(briefWrap).toHaveClass(/ai-touched/);

    // Human edit clears the ai-touched marker
    await page.locator('#headlineInput').fill('AI Generated headline (Human edited)');
    await expect(headlineWrap).not.toHaveClass(/ai-touched/);

    // Close drawer
    await page.locator('#aidClose').click();
    await expect(drawer).not.toHaveClass(/open/);
  });

  test('AI Publish Gate prevents routine story with unreviewed AI changes from publishing, but allows breaking stories', async ({ page }) => {
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');

    // Fill raw and apply AI suggestions without human edits
    await page.locator('#aiRaw').fill('Severe cyclonic storm approaching coastal districts');
    await page.locator('#aiGenerateBtn').click();

    await expect(page.locator('#aiDrawer')).toHaveClass(/open/, { timeout: 10000 });
    await page.locator('#aidApplyHeadline').click();
    await page.locator('#aidApplyBrief').click();
    await page.locator('#aidApplyCategory').click();
    await page.locator('#aidClose').click();

    // Step 3: Organize & access
    await page.click('.stp[data-go="3"]');
    await expect(page.locator('.stp.active .stp-label')).toHaveText('Organize & access');

    // Ensure priority is routine
    const prioritySelect = page.locator('select[wire\\:model\\.live="priority"]');
    await prioritySelect.selectOption('routine');

    // Step 4: Review & publish
    await page.click('#nextBtn');
    await expect(page.locator('.stp.active .stp-label')).toHaveText('Review & publish');

    // Attempt to publish routine story with unreviewed AI changes
    const publishBtn = page.locator('#publishBtn');
    await expect(publishBtn).toBeVisible();
    await publishBtn.click();

    // Expect AI guardrail rejection toast
    await expect(page.getByText(/AI-touched fields require review before publish/i)).toBeVisible({ timeout: 5000 });

    // Switch priority to flash (breaking) to bypass AI gate
    await page.click('.stp[data-go="3"]');
    await expect(page.locator('.stp.active .stp-label')).toHaveText('Organize & access');
    await prioritySelect.selectOption('flash');

    // Return to Step 4 and publish
    await page.click('#nextBtn');
    await expect(page.locator('.stp.active .stp-label')).toHaveText('Review & publish');
    await publishBtn.click();

    // Publish succeeds
    await expect(page.getByText(/successfully published/i)).toBeVisible({ timeout: 5000 });
  });

  test('AI generation records tokens and is reflected on /admin/ai-settings', async ({ page }) => {
    // Generate AI draft in Add News
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');

    await page.locator('#aiRaw').fill('Dhaka international trade fair schedule announced');
    await page.locator('#aiGenerateBtn').click();
    await expect(page.locator('#aiDrawer')).toHaveClass(/open/, { timeout: 10000 });

    // Navigate to AI Settings
    await page.goto('/admin/ai-settings');
    await page.waitForLoadState('networkidle');

    // Usage meter and desk breakdown render
    await expect(page.locator('#usageNow')).toBeVisible();
    await expect(page.locator('#usageFill')).toBeVisible();
    await expect(page.locator('.desk-table')).toContainText('English News');
  });

  test('AI kill switch blocks generation requests with clear notice', async ({ page }) => {
    // Enable AI kill switch via tinker
    execSync('php artisan tinker --execute="\\Illuminate\\Support\\Facades\\DB::table(\'settings\')->updateOrInsert([\'key\' => \'ai.desk\'], [\'value\' => json_encode([\'killed\' => true])]);"', { stdio: 'ignore' });

    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');

    await page.locator('#aiRaw').fill('Attempt generation while kill switch is on');
    await page.locator('#aiGenerateBtn').click();

    // Clean error notification
    await expect(page.getByText(/kill switch is ON/i)).toBeVisible({ timeout: 5000 });

    // Restore settings
    execSync('php tests/e2e/helpers/seed-data.php ai', { stdio: 'ignore' });
  });
});
