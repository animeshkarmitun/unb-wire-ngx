import { test, expect } from '@playwright/test';
import { loginAs, USERS } from './helpers/auth';
import { execSync } from 'child_process';

function getConcurrencyStoryId(): string {
  return execSync(
    'php artisan tinker --execute="echo \\App\\Models\\Story::where(\'public_id\', \'01JCONCURRENCYTEST00000001\')->value(\'id\');"',
    { encoding: 'utf-8' }
  ).trim();
}

test.describe('Editorial Concurrency, Soft Locking & Shift Handover (M10-CONC / COS-3)', () => {
  test.beforeEach(async () => {
    try {
      execSync('php tests/e2e/helpers/seed-data.php concurrency', { stdio: 'ignore' });
    } catch (_) {}
  });

  test('News List workflow drawer displays owner, allows shift takeover, and logs audit note', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/news/en');
    await page.waitForLoadState('networkidle');

    // Find and select the concurrency test story
    const storyRow = page.locator('tr', { hasText: 'Sylhet flood relief dispatch operation underway' }).first();
    await expect(storyRow).toBeVisible({ timeout: 10000 });

    const titleLink = storyRow.locator('.news-title');
    await titleLink.click();

    // Drawer opens
    const drawer = page.locator('aside.wfd');
    await expect(drawer).toHaveClass(/open/, { timeout: 5000 });

    // Verify owner section displays previous owner (Shohel Ahmed)
    const ownerMeta = drawer.locator('.wfd-owner');
    await expect(ownerMeta).toBeVisible();
    await expect(ownerMeta).toContainText('Shohel Ahmed');
    await expect(ownerMeta).toContainText('on shift until');

    // Take over button is visible for non-owner
    const takeOverBtn = ownerMeta.locator('button', { hasText: 'Take over' });
    await expect(takeOverBtn).toBeVisible();

    // Click Take over
    await takeOverBtn.click();

    // Verify success toast
    await expect(page.getByText(/Ownership taken over successfully/i)).toBeVisible({ timeout: 5000 });

    // Verify owner section updates immediately
    await expect(ownerMeta).toContainText('You took over this story');
    await expect(takeOverBtn).not.toBeVisible();

    // Verify system note in internal notes thread
    const notesList = drawer.locator('.nt-list');
    await expect(notesList).toContainText('Taken over by Test User from Shohel Ahmed — shift handover');
  });

  test('Add News editor wizard reflects soft lock ownership and provides takeover handover', async ({ page }) => {
    const storyId = getConcurrencyStoryId();
    expect(storyId).toBeTruthy();

    await loginAs(page, 'admin');
    await page.goto(`/admin/add-news?id=${storyId}`);
    await page.waitForLoadState('networkidle');

    // Workflow strip displays previous owner with green avatar
    const wfStrip = page.locator('#wfStrip');
    await expect(wfStrip).toBeVisible({ timeout: 5000 });
    await expect(page.locator('#wfOwner')).toContainText('Shohel Ahmed');

    // Takeover button is visible
    const takeOverBtn = page.locator('#wfTakeOverBtn');
    await expect(takeOverBtn).toBeVisible();

    // Click takeover button
    await takeOverBtn.click();

    // Toast confirms ownership
    await expect(page.getByText(/You have taken ownership of this story/i)).toBeVisible({ timeout: 5000 });

    // Strip updates to Test User and button disappears
    await expect(page.locator('#wfOwner')).toContainText('Test User');
    await expect(takeOverBtn).not.toBeVisible();
  });

  test('Optimistic version mismatch detects conflict and blocks stale takeover', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/news/en');
    await page.waitForLoadState('networkidle');

    // Open drawer with story version 1 loaded
    const storyRow = page.locator('tr', { hasText: 'Sylhet flood relief dispatch operation underway' }).first();
    await expect(storyRow).toBeVisible({ timeout: 10000 });
    await storyRow.locator('.news-title').click();

    const drawer = page.locator('aside.wfd');
    await expect(drawer).toHaveClass(/open/, { timeout: 5000 });

    const takeOverBtn = drawer.locator('.wfd-owner button', { hasText: 'Take over' });
    await expect(takeOverBtn).toBeVisible();

    // Concurrently bump DB story version behind the scenes (simulating another user's save)
    execSync('php tests/e2e/helpers/seed-data.php concurrency-stale', { stdio: 'ignore' });

    // Attempt takeover with stale version
    await takeOverBtn.click();

    // Conflict error banner displays
    await expect(page.getByText(/Version conflict — this story was updated by another user/i)).toBeVisible({ timeout: 5000 });
  });

  test('Handover triggers notification for previous owner', async ({ page }) => {
    // 1. Admin takes over story from Shohel
    await loginAs(page, 'admin');
    await page.goto('/admin/news/en');
    await page.waitForLoadState('networkidle');

    const storyRow = page.locator('tr', { hasText: 'Sylhet flood relief dispatch operation underway' }).first();
    await storyRow.locator('.news-title').click();

    const drawer = page.locator('aside.wfd');
    await expect(drawer).toHaveClass(/open/, { timeout: 5000 });
    await drawer.locator('.wfd-owner button', { hasText: 'Take over' }).click();
    await expect(page.getByText(/Ownership taken over successfully/i)).toBeVisible({ timeout: 5000 });

    // 2. Log in as previous owner (Shohel Ahmed)
    await page.context().clearCookies();
    await loginAs(page, 'editor');

    // 3. Visit notifications center
    await page.goto('/admin/notifications');
    await page.waitForLoadState('networkidle');

    // Expect handover notification in notification list
    await expect(page.locator('main').getByText('Story ownership transferred')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('main').getByText('Sylhet flood relief dispatch operation underway')).toBeVisible();
  });

  test('Handover records audit event visible in Audit Log Browser', async ({ page }) => {
    // 1. Admin takes over story
    await loginAs(page, 'admin');
    await page.goto('/admin/news/en');
    await page.waitForLoadState('networkidle');

    const storyRow = page.locator('tr', { hasText: 'Sylhet flood relief dispatch operation underway' }).first();
    await storyRow.locator('.news-title').click();

    const drawer = page.locator('aside.wfd');
    await expect(drawer).toHaveClass(/open/, { timeout: 5000 });
    await drawer.locator('.wfd-owner button', { hasText: 'Take over' }).click();
    await expect(page.getByText(/Ownership taken over successfully/i)).toBeVisible({ timeout: 5000 });

    // 2. Navigate to Audit Log Browser
    await page.goto('/admin/audit');
    await page.waitForLoadState('networkidle');

    // Table renders the handover audit record
    const auditTable = page.locator('table');
    await expect(auditTable).toBeVisible({ timeout: 10000 });
    await expect(auditTable).toContainText(/handover/i);
    await expect(auditTable).toContainText('Shohel Ahmed');
    await expect(auditTable).toContainText('Test User');
  });
});
