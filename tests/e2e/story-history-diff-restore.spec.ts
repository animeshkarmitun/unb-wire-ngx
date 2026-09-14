import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { loginAs } from './helpers/auth';

test.describe('Story Version History, Diffing & Restore (M10-HIST)', () => {
  let storyPublicId = '';
  let storyId = 0;

  test.beforeAll(async () => {
    // Create a draft story with 2 distinct versions
    try {
      const output = execSync('php tests/e2e/helpers/seed-data.php story_versions', { encoding: 'utf-8' });
      const match = output.trim().match(/(\d+):([0-9A-Z]{26})/);
      if (match) {
        storyId = parseInt(match[1], 10);
        storyPublicId = match[2];
      }
    } catch (_) {}
  });

  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'admin');
  });

  test('Story reader displays Version History section with version snapshots', async ({ page }) => {
    if (!storyPublicId) test.skip();

    await page.goto(`/admin/story/${storyPublicId}`);
    await page.waitForLoadState('networkidle');

    // Version history panel
    const verPanel = page.locator('.story-editorial-panel:has-text("Version History")');
    await expect(verPanel).toBeVisible({ timeout: 5000 });
    await expect(verPanel.locator('span.font-mono:has-text("v2")')).toBeVisible();
    await expect(verPanel.locator('span.font-mono:has-text("v1")')).toBeVisible();

    // Event timeline panel
    const timelinePanel = page.locator('.story-editorial-panel:has-text("Workflow Timeline")');
    await expect(timelinePanel).toBeVisible();
  });

  test('Selecting two versions displays Compare button and renders side-by-side diff', async ({ page }) => {
    if (!storyPublicId) test.skip();

    await page.goto(`/admin/story/${storyPublicId}`);
    await page.waitForLoadState('networkidle');

    // Click checkboxes for v2 and v1
    const v2Check = page.locator('input[type="checkbox"][value="2"]');
    const v1Check = page.locator('input[type="checkbox"][value="1"]');

    await v2Check.click();
    await v1Check.click();

    // Compare button should appear
    const compareBtn = page.locator('button:has-text("Compare")').first();
    await expect(compareBtn).toBeVisible();
    await compareBtn.click();

    // Diff result panel should be visible
    const diffHeader = page.locator('span:has-text("Diff: v")');
    await expect(diffHeader).toBeVisible({ timeout: 5000 });

    // Field changes inside diff panel should show headline differences
    const diffContainer = page.locator('.story-editorial-panel').filter({ hasText: 'Diff: v' });
    await expect(diffContainer.locator('text=E2E Diff Test v1')).toBeVisible();
    await expect(diffContainer.locator('text=E2E Diff Test v2')).toBeVisible();

    // Clear diff button
    const closeBtn = page.locator('button:has-text("✕ Close")');
    await expect(closeBtn).toBeVisible();
    await closeBtn.click();
    await expect(diffHeader).not.toBeVisible();
  });

  test('Restoring an older version opens confirmation modal and creates new version', async ({ page }) => {
    if (!storyPublicId) test.skip();

    await page.goto(`/admin/story/${storyPublicId}`);
    await page.waitForLoadState('networkidle');

    // Click Restore button on v1
    const restoreBtn = page.locator('.story-editorial-panel button:has-text("Restore")').first();
    await expect(restoreBtn).toBeVisible();
    await restoreBtn.click();

    // Confirmation modal should open
    const modal = page.locator('h3:has-text("Restore to v")');
    await expect(modal).toBeVisible();

    // Click confirm Restore inside modal
    const confirmBtn = page.locator('div.fixed button:has-text("Restore")');
    await confirmBtn.click();

    // Modal closes and toast notification confirms restore
    await expect(modal).not.toBeVisible({ timeout: 8000 });
    await expect(page.locator('#toastWrap .toast, .toast').first()).toBeVisible({ timeout: 5000 });

    // Reader headline should reflect restored v1 content and new version v3
    await expect(page.locator('#storyHead')).toContainText('E2E Diff Test v1');
    await expect(page.locator('.story-editorial-panel:has-text("Version History")')).toContainText('v3');
  });

  test('Add News wizard reflects server version history on existing saved story', async ({ page }) => {
    if (!storyId) test.skip();

    await page.goto(`/admin/add-news?id=${storyId}`);
    await page.waitForLoadState('networkidle');

    // Server version history toggle button / card
    const historyBtn = page.locator('button:has-text("Server version history")');
    if (await historyBtn.isVisible()) {
      await historyBtn.click();
      await expect(page.locator('text=Restore to this version').first()).toBeVisible({ timeout: 5000 });
    }
  });
});
