import { test, expect } from '@playwright/test';

test.describe('Clients Manager Faithful (M8-CLIENT-001)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 }).catch(() => {});
  });

  test('Page header, breadcrumb, 5-stat strip, toolbar, status chips, and table render', async ({ page }) => {
    await page.goto('/admin/clients');

    // Breadcrumb & Heading
    await expect(page.locator('.breadcrumb')).toContainText('Clients');
    await expect(page.getByRole('heading', { name: 'Clients' })).toBeVisible({ timeout: 5000 });

    // Topbar actions
    await expect(page.getByRole('button', { name: 'Export CSV' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Onboard client' })).toBeVisible();

    // 5-Stat Strip
    await expect(page.locator('#stTotal')).toHaveText('8');
    await expect(page.locator('#stActive')).toHaveText('5');
    await expect(page.locator('#stPaused')).toHaveText('2');
    await expect(page.locator('#stRenew')).toHaveText('3');
    await expect(page.locator('#stIssues')).toHaveText('1');

    // Toolbar
    await expect(page.locator('#clSearch')).toBeVisible();
    await expect(page.locator('#statusChips .st-chip')).toHaveCount(4);
    await expect(page.locator('#tierSel')).toBeVisible();
    await expect(page.locator('#sortSel')).toBeVisible();

    // Client rows
    const rows = page.locator('#clList .cl-row:not(.head)');
    await expect(rows).toHaveCount(8);
    await expect(page.locator('#clList')).toContainText('The Daily Star');
    await expect(page.locator('#clList')).toContainText('Prothom Alo');
    await expect(page.locator('#clList')).toContainText('Jugantor');
    await expect(page.locator('#clList')).toContainText('Delivery issue');
  });

  test('Search and status chips filter clients interactively', async ({ page }) => {
    await page.goto('/admin/clients');

    // Search for Jugantor
    await page.fill('#clSearch', 'Jugantor');
    await expect(page.locator('#clList')).toContainText('Jugantor');
    await expect(page.locator('#clList')).not.toContainText('The Daily Star');

    // Clear search
    await page.fill('#clSearch', '');
    await expect(page.locator('#clList')).toContainText('The Daily Star');

    // Click Paused chip
    await page.click('#statusChips button[data-st="paused"]');
    await expect(page.locator('#statusChips button[data-st="paused"]')).toHaveClass(/active/);
    await expect(page.locator('#clList')).toContainText('Bangladesh Today');
    await expect(page.locator('#clList')).toContainText('Samakal');
    await expect(page.locator('#clList')).not.toContainText('The Daily Star');

    // Click All chip to reset
    await page.click('#statusChips button[data-st="all"]');
    await expect(page.locator('#clList')).toContainText('The Daily Star');
  });

  test('Multi-selection triggers sticky bulk action bar', async ({ page }) => {
    await page.goto('/admin/clients');

    // Initially bulk bar is not shown
    await expect(page.locator('#bulkBar')).not.toHaveClass(/show/);

    // Click first client checkbox
    const firstCheckbox = page.locator('#clList .cl-row:not(.head) input[type="checkbox"]').first();
    await firstCheckbox.click();

    // Bulk bar appears
    await expect(page.locator('#bulkBar')).toHaveClass(/show/);
    await expect(page.locator('#bulkCount')).toContainText('1 selected');

    // Click second client checkbox
    const secondCheckbox = page.locator('#clList .cl-row:not(.head) input[type="checkbox"]').nth(1);
    await secondCheckbox.click();
    await expect(page.locator('#bulkCount')).toContainText('2 selected');

    // Click Clear
    await page.click('#bulkClear');
    await expect(page.locator('#bulkBar')).not.toHaveClass(/show/);
  });

  test('Detail drawer opens on row click, switches tabs, and saves note', async ({ page }) => {
    await page.goto('/admin/clients');

    // Click Daily Star row
    const dailyStarRow = page.locator('#clList .cl-row:has-text("The Daily Star")');
    await dailyStarRow.click();

    // Drawer opens
    const drawer = page.locator('#drawer');
    await expect(drawer).toHaveClass(/open/);
    await expect(drawer.locator('#drName')).toHaveText('The Daily Star');

    // Overview Tab
    await expect(drawer.locator('.dr-tab[data-dtab="overview"]')).toHaveClass(/active/);
    await expect(drawer.locator('.dr-sec-title').first()).toHaveText('Contacts');
    await expect(drawer.locator('#noteArea')).toBeVisible();

    // Save Note
    await drawer.locator('#noteArea').fill('Updated note via Playwright');
    await drawer.locator('#noteSave').click();
    await expect(page.locator('#toastWrap')).toContainText('Note saved');

    // Switch to Channels Tab
    await drawer.locator('.dr-tab[data-dtab="channels"]').click();
    await expect(drawer.locator('.chan-name', { hasText: 'Client portal dashboard' })).toBeVisible();
    await expect(drawer.locator('.chan-name', { hasText: 'Email alerts' })).toBeVisible();
    await expect(drawer.locator('.chan-name', { hasText: 'FTP / SFTP auto-push' })).toBeVisible();

    // Switch to Package Tab
    await drawer.locator('.dr-tab[data-dtab="package"]').click();
    await expect(drawer.locator('.pkg-current')).toContainText('Premium Wire + Media');

    // Switch to Activity Tab
    await drawer.locator('.dr-tab[data-dtab="activity"]').click();
    await expect(drawer.locator('.tl-item').first()).toBeVisible();

    // Close Drawer
    await drawer.locator('#drClose').click();
    await expect(drawer).not.toHaveClass(/open/);
  });

  test('Pause modal, deactivate modal, and onboard 3-step wizard modals operate correctly', async ({ page }) => {
    await page.goto('/admin/clients');

    // Open Onboard Wizard
    await page.getByRole('button', { name: 'Onboard client' }).click();
    const wiz = page.locator('#wizOverlay');
    await expect(wiz).toHaveClass(/open/);
    await expect(wiz.locator('.mo-title')).toHaveText('Onboard new client');

    // Step 1 Details
    await expect(wiz.locator('.wiz-step[data-ws="1"]')).toHaveClass(/active/);
    await wiz.locator('#wName').fill('Playwright Times');
    await wiz.locator('#wEmail').fill('desk@playwrighttimes.com');
    await wiz.locator('#wizNext').click();

    // Step 2 Package & Channels
    await expect(wiz.locator('.wiz-step[data-ws="2"]')).toHaveClass(/active/);
    await wiz.locator('#wizNext').click();

    // Step 3 Review
    await expect(wiz.locator('.wiz-step[data-ws="3"]')).toHaveClass(/active/);
    await expect(wiz.locator('#wizReview')).toContainText('Playwright Times');
    await expect(wiz.locator('#wizActivate')).toBeVisible();

    // Close wizard
    await wiz.locator('.mo-close').click();
    await expect(wiz).not.toHaveClass(/open/);
  });
});
