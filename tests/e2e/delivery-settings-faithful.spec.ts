import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('Delivery Settings Manager Faithful (M8-DELIV-001)', () => {
  test.beforeAll(async () => {
    execSync('php artisan db:seed --class=DatabaseSeeder', { stdio: 'ignore' });
  });

  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'nahar@unbnews.org');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 }).catch(() => {});
  });

  test('Page header, masthead context, Dhaka clock, client switcher, and 5 cards render correctly', async ({ page }) => {
    await page.goto('/admin/delivery-settings');

    // Breadcrumb & Heading
    await expect(page.locator('.breadcrumb')).toContainText('Delivery settings');
    await expect(page.getByRole('heading', { name: 'Delivery settings' })).toBeVisible({ timeout: 5000 });
    await expect(page.locator('.page-sub')).toContainText('Zero-touch delivery');

    // Masthead Context Bar
    await expect(page.locator('.mast-context')).toBeVisible();
    await expect(page.locator('.portal-tag')).toHaveText('Client Portal');
    await expect(page.locator('#delivClockTime')).not.toBeEmpty();
    await expect(page.locator('#delivClockDate')).not.toBeEmpty();

    // Client Context
    await expect(page.locator('#clientSelect')).toBeVisible();
    await expect(page.locator('#clientNameDisplay')).toContainText('The Daily Star');
    await expect(page.locator('#clientTierDisplay')).toContainText('★ Premium');

    // Card 1: Auto-push (FTP / SFTP)
    await expect(page.getByText('Auto-push (FTP / SFTP)')).toBeVisible();
    await expect(page.locator('#pushMaster')).toBeChecked();
    await expect(page.locator('#pushDot')).toHaveClass(/live/);
    await expect(page.locator('#connectionEndpointText')).toContainText('Connected to');
    await expect(page.locator('#lastPushInfo')).toContainText('Last successful push');
    await expect(page.locator('#testBtn')).toBeVisible();
    await expect(page.locator('#credBtn')).toBeVisible();
    await expect(page.locator('#wireFmt')).toHaveValue('NewsML-G2 (XML)');
    await expect(page.locator('#pushSchedule')).toHaveValue('Instantly on publish');

    // Card 2: API Access
    await expect(page.getByText('API access')).toBeVisible();
    await expect(page.locator('#apiEndpoint')).toBeVisible();
    await expect(page.locator('#apiKey')).toHaveValue(/unb_live_••••••••••••/);
    await expect(page.locator('#revealKey')).toHaveText('Reveal');
    await expect(page.locator('#regenKey')).toHaveText('Regenerate');
    await expect(page.locator('#webhookUrl')).toBeVisible();
    await expect(page.locator('#notifyBreaking')).toBeChecked();

    // Card 3: Email Alerts
    await expect(page.getByText('Email alerts')).toBeVisible();
    await expect(page.locator('#emailChips')).toBeVisible();
    await expect(page.locator('#emailChips .email-chip').first()).toBeVisible();
    await expect(page.locator('#emailInput')).toBeVisible();
    await expect(page.locator('#alertBreaking')).toBeChecked();

    // Card 4: Download & License History
    await expect(page.getByText('Download & license history')).toBeVisible();
    await expect(page.locator('#histCsv')).toBeVisible();
    await expect(page.locator('#histBody tr').first()).toBeVisible();
    await expect(page.locator('#histBody .lic-pill').first()).toBeVisible();
    await expect(page.locator('.audit-note')).toContainText('license compliance audits');

    // Card 5: Engine Dispatch Rules
    await expect(page.getByText('Engine dispatch rules')).toBeVisible();
    await expect(page.locator('#retryAttempts')).toHaveValue('3');
    await expect(page.locator('#backoffSeconds')).toHaveValue('60');
    await expect(page.locator('#autoPauseAfter')).toHaveValue('5');
    await expect(page.locator('#atLeastOnce')).toBeChecked();
  });

  test('Auto-push master toggle, test connection simulation, and credentials drawer', async ({ page }) => {
    await page.goto('/admin/delivery-settings');

    // 1. Test Connection Simulation
    await page.locator('#testBtn').click();
    await expect(page.locator('#testBtn')).toHaveText('✓ Connection OK');
    await expect(page.locator('.toast', { hasText: 'SFTP connection successful' }).last()).toBeVisible();

    // 2. Toggle Credentials Drawer
    const credFields = page.locator('#credFields');
    await expect(credFields).toBeHidden();

    await page.locator('#credBtn').click();
    await expect(credFields).toBeVisible();

    // Edit host & save credentials
    await page.locator('#sftpHost').fill('ftp.stardelivery.com');
    await page.locator('#credSave').click();
    await expect(credFields).toBeHidden();
    await expect(page.locator('.toast', { hasText: 'Credentials saved' }).last()).toBeVisible();
    await expect(page.locator('#connectionEndpointText')).toContainText('ftp.stardelivery.com');

    // 3. Save Push Settings
    await page.locator('#wireFmt').selectOption('JSON (UNB v1)');
    await page.locator('#pushSave').click();
    await expect(page.locator('.toast', { hasText: 'Push settings saved' }).last()).toBeVisible();

    // 4. Toggle Auto-Push Master Switch
    await page.locator('#pushMaster').click();
    await expect(page.locator('.toast', { hasText: 'Auto-push paused' }).last()).toBeVisible();
    await expect(page.locator('#pushDot')).toHaveClass(/paused/);

    await page.locator('#pushMaster').click();
    await expect(page.locator('.toast', { hasText: 'Auto-push enabled' }).last()).toBeVisible();
    await expect(page.locator('#pushDot')).toHaveClass(/live/);
  });

  test('API key reveal toggle, 2-step regeneration flow, and webhook settings save', async ({ page }) => {
    await page.goto('/admin/delivery-settings');

    // 1. Reveal and Hide API key
    const apiKeyInput = page.locator('#apiKey');
    await expect(apiKeyInput).toHaveValue(/••••••••••••/);

    await page.locator('#revealKey').click();
    await expect(page.locator('#revealKey')).toHaveText('Hide');
    await expect(apiKeyInput).not.toHaveValue(/••••••••••••/);

    await page.locator('#revealKey').click();
    await expect(page.locator('#revealKey')).toHaveText('Reveal');
    await expect(apiKeyInput).toHaveValue(/••••••••••••/);

    // 2. 2-Step Key Regeneration
    const regenBtn = page.locator('#regenKey');
    await expect(regenBtn).toHaveText('Regenerate');

    // Step 1 click
    await regenBtn.click();
    await expect(regenBtn).toHaveText('Click again to confirm');

    // Step 2 click
    await regenBtn.click();
    await expect(page.locator('.toast', { hasText: 'Old key revoked' }).last()).toBeVisible();
    await expect(regenBtn).toHaveText('Regenerate');

    // 3. Save Webhook Settings
    await page.locator('#webhookUrl').fill('https://newsdesk.dailystar.com/webhooks/unb-live');
    await page.locator('#notifyEmbargoed').check();
    await page.locator('#apiSave').click();
    await expect(page.locator('.toast', { hasText: 'API settings saved' }).last()).toBeVisible();
  });

  test('Email alert recipient chips add/remove and alert settings save', async ({ page }) => {
    await page.goto('/admin/delivery-settings');

    const emailInput = page.locator('#emailInput');
    const emailAddBtn = page.locator('#emailAdd');

    // 1. Invalid email validation toast
    await emailInput.fill('not-an-email');
    await emailAddBtn.click();
    await expect(page.locator('.toast', { hasText: 'Enter a valid email address' }).last()).toBeVisible();

    // 2. Add valid email chip
    await emailInput.fill('nightdesk@dailystar.com');
    await emailAddBtn.click();
    await expect(page.locator('.toast', { hasText: 'nightdesk@dailystar.com added to alerts' }).last()).toBeVisible();
    await expect(page.locator('#emailChips')).toContainText('nightdesk@dailystar.com');

    // 3. Remove email chip
    const chip = page.locator('#emailChips .email-chip', { hasText: 'nightdesk@dailystar.com' });
    await chip.locator('button').click();
    await expect(page.locator('.toast', { hasText: 'Recipient removed' }).last()).toBeVisible();
    await expect(page.locator('#emailChips')).not.toContainText('nightdesk@dailystar.com');

    // 4. Save alert settings
    await page.locator('#alertSavedSearch').check();
    await page.locator('#alertSave').click();
    await expect(page.locator('.toast', { hasText: 'Alert settings saved' }).last()).toBeVisible();
  });

  test('Client context switching, export CSV action, and engine dispatch rules', async ({ page }) => {
    await page.goto('/admin/delivery-settings');

    // 1. Client Switcher
    const clientSelect = page.locator('#clientSelect');
    const options = await clientSelect.locator('option').all();
    if (options.length > 1) {
      const secondClientId = await options[1].getAttribute('value');
      if (secondClientId) {
        await clientSelect.selectOption(secondClientId);
        await expect(page.locator('#clientNameDisplay')).not.toBeEmpty();
      }
    }

    // Switch back to first client (DST)
    const firstClientId = await options[0].getAttribute('value');
    if (firstClientId) {
      await clientSelect.selectOption(firstClientId);
    }
    await expect(page.locator('#clientNameDisplay')).toContainText('The Daily Star');

    // 2. Export CSV
    await page.locator('#histCsv').click();
    await expect(page.locator('#histCsv')).toHaveText('✓ Exported');

    // 3. Engine Dispatch Rules
    await page.locator('#retryAttempts').fill('4');
    await page.locator('#backoffSeconds').fill('90');
    await page.locator('#engineSave').click();
    await expect(page.locator('.toast', { hasText: 'Delivery settings saved' }).last()).toBeVisible();
  });
});
