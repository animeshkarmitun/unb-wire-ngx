import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { loginAs } from './helpers/auth';

test.describe('Story Version History, Diffing & Restore (M10-HIST)', () => {
  let storyPublicId = '';
  let storyId = 0;

  test.beforeAll(() => {
    const output = execSync('php tests/e2e/helpers/seed-data.php story_versions', { encoding: 'utf-8' });
    const match = output.trim().match(/(\d+):([0-9A-Z]{26})/);
    if (!match) {
      throw new Error('seed-data.php story_versions did not return id:public_id — refusing to skip');
    }
    storyId = parseInt(match[1], 10);
    storyPublicId = match[2];
  });

  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'admin');
  });

  test('Story reader displays Version History section with version snapshots', async ({ page }) => {
    expect(storyPublicId).not.toBe('');
    await page.goto(`/admin/story/${storyPublicId}`);
    await page.waitForLoadState('networkidle');

    const verPanel = page.locator('.story-editorial-panel:has-text("Version History")');
    await expect(verPanel).toBeVisible({ timeout: 5000 });
    await expect(verPanel.locator('span.font-mono:has-text("v2")')).toBeVisible();
    await expect(verPanel.locator('span.font-mono:has-text("v1")')).toBeVisible();

    const timelinePanel = page.locator('.story-editorial-panel:has-text("Workflow Timeline")');
    await expect(timelinePanel).toBeVisible();
  });

  test('Selecting two versions displays Compare button and renders side-by-side diff', async ({ page }) => {
    expect(storyPublicId).not.toBe('');
    await page.goto(`/admin/story/${storyPublicId}`);
    await page.waitForLoadState('networkidle');

    const v2Check = page.locator('input[type="checkbox"][value="2"]');
    const v1Check = page.locator('input[type="checkbox"][value="1"]');

    await v2Check.click();
    await v1Check.click();

    const compareBtn = page.locator('button:has-text("Compare")').first();
    await expect(compareBtn).toBeVisible();
    await compareBtn.click();

    const diffHeader = page.locator('span:has-text("Diff: v")');
    await expect(diffHeader).toBeVisible({ timeout: 5000 });

    const diffContainer = page.locator('.story-editorial-panel').filter({ hasText: 'Diff: v' });
    await expect(diffContainer.locator('text=E2E Diff Test v1')).toBeVisible();
    await expect(diffContainer.locator('text=E2E Diff Test v2')).toBeVisible();

    const closeBtn = page.locator('button:has-text("✕ Close")');
    await expect(closeBtn).toBeVisible();
    await closeBtn.click();
    await expect(diffHeader).not.toBeVisible();
  });

  test('Restoring an older version opens confirmation modal and creates new version', async ({ page }) => {
    expect(storyPublicId).not.toBe('');
    await page.goto(`/admin/story/${storyPublicId}`);
    await page.waitForLoadState('networkidle');

    const restoreBtn = page.locator('.story-editorial-panel button:has-text("Restore")').first();
    await expect(restoreBtn).toBeVisible();
    await restoreBtn.click();

    const modal = page.locator('h3:has-text("Restore to v")');
    await expect(modal).toBeVisible();

    const confirmBtn = page.locator('div.fixed button:has-text("Restore")');
    await confirmBtn.click();

    await expect(modal).not.toBeVisible({ timeout: 8000 });
    await expect(page.locator('#toastWrap .toast, .toast').first()).toBeVisible({ timeout: 5000 });

    // Server truth: the latest version row has the restored content.
    const versionRow = execSync(
      `php artisan tinker --execute="echo \\App\\Models\\StoryVersion::where('story_id', ${storyId})->orderByDesc('version')->value('headline');"`,
      { encoding: 'utf-8' }
    ).toString().trim();
    expect(versionRow).toBe('E2E Diff Test v1');

    await expect(page.locator('#storyHead')).toContainText('E2E Diff Test v1');
    await expect(page.locator('.story-editorial-panel:has-text("Version History")')).toContainText('v3');
  });

  test('Add News wizard reflects server version history on existing saved story', async ({ page }) => {
    expect(storyId).toBeGreaterThan(0);
    await page.goto(`/admin/add-news?id=${storyId}`);
    await page.waitForLoadState('networkidle');

    const historyBtn = page.locator('button:has-text("Server version history")');
    await expect(historyBtn).toBeVisible({ timeout: 5000 });
    await historyBtn.click();
    await expect(page.locator('text=Restore to this version').first()).toBeVisible({ timeout: 5000 });
  });
});
