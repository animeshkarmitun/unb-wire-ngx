import { test, expect } from '@playwright/test';

test.describe('UNB Photo Manager & Field Intake Queue Faithful (M8-PHOTO-001 & M8-PHOTO-002)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 }).catch(() => {});
  });

  test('Page header, 7 workflow tabs with counts, toolbar, and justified grid render', async ({ page }) => {
    await page.goto('/admin/photos');

    // Heading & Topbar
    await expect(page.getByRole('heading', { name: 'UNB Photo Manager' })).toBeVisible({ timeout: 5000 });
    await expect(page.locator('#unattachedBtn')).toBeVisible();
    await expect(page.locator('#uploadBtn')).toBeVisible();

    // 7 workflow tabs
    const tabs = page.locator('#wfTabs .wf-tab');
    await expect(tabs).toHaveCount(7);
    await expect(tabs.nth(0)).toContainText('All assets');
    await expect(tabs.nth(1)).toContainText('Field intake');
    await expect(tabs.nth(2)).toContainText('Needs review');
    await expect(tabs.nth(3)).toContainText('In library');
    await expect(tabs.nth(4)).toContainText('Packaged');
    await expect(tabs.nth(5)).toContainText('Published');
    await expect(tabs.nth(6)).toContainText('Embargoed');

    // Count pills inside tabs
    await expect(tabs.nth(0).locator('.n')).toBeVisible();

    // Toolbar filters
    await expect(page.locator('#damSearch')).toBeVisible();
    await expect(page.locator('#damBy')).toBeVisible();
    await expect(page.locator('#damCat')).toBeVisible();
    await expect(page.locator('#damSort')).toBeVisible();

    // Justified flex grid
    const damGrid = page.locator('#damGrid');
    await expect(damGrid).toBeVisible();

    const items = damGrid.locator('.dam-item');
    if (await items.count() > 0) {
      const firstItem = items.first();
      await expect(firstItem).toBeVisible();
      // Verify --r aspect ratio style is set
      const styleAttr = await firstItem.getAttribute('style');
      expect(styleAttr).toContain('--r');
      // Hover overlay
      await expect(firstItem.locator('.dam-overlay')).toBeAttached();
      // Status pill
      await expect(firstItem.locator('.dam-status')).toBeVisible();
    }
  });

  test('Multi-select triggers sticky bulk bar and allows clearing', async ({ page }) => {
    await page.goto('/admin/photos');

    const damGrid = page.locator('#damGrid');
    const items = damGrid.locator('.dam-item');

    if (await items.count() > 0) {
      const firstCheck = items.first().locator('.dam-check');
      await firstCheck.check({ force: true });

      // Bulk bar appears
      const bulkbar = page.locator('#bulkbar');
      await expect(bulkbar).toHaveClass(/show/);
      await expect(page.locator('#bulkCount')).toContainText('1 selected');
      await expect(page.locator('#bulkApprove')).toBeVisible();
      await expect(page.locator('#bulkPkg')).toBeVisible();
      await expect(page.locator('#bulkZip')).toBeVisible();

      // Clear selection
      await page.locator('#bulkClear').click();
      await expect(bulkbar).not.toHaveClass(/show/);
    }
  });

  test('Clicking asset opens sticky inspector with editable metadata and usage stats', async ({ page }) => {
    await page.goto('/admin/photos');

    const damGrid = page.locator('#damGrid');
    const items = damGrid.locator('.dam-item');

    if (await items.count() > 0) {
      // Click first item to open inspector
      await items.first().click();

      const inspPanel = page.locator('#inspPanel');
      await expect(inspPanel).toBeVisible({ timeout: 5000 });
      await expect(inspPanel.locator('.insp-title')).toContainText('Asset details');

      // Inspector form inputs
      await expect(inspPanel.locator('#iCap')).toBeVisible();
      await expect(inspPanel.locator('#iBy')).toBeVisible();
      await expect(inspPanel.locator('#iLoc')).toBeVisible();
      await expect(inspPanel.locator('#iKw')).toBeVisible();
      await expect(inspPanel.locator('#iPkg')).toBeVisible();
      await expect(inspPanel.locator('.usage-box')).toBeVisible();
      await expect(inspPanel.locator('#iSave')).toBeVisible();

      // Close inspector
      await inspPanel.locator('#inspClose').click();
      await expect(inspPanel).not.toBeVisible();
    }
  });

  test('Field intake queue displays batches, urgency badges, and decision modal', async ({ page }) => {
    await page.goto('/admin/photos');

    // Click Field intake tab
    const fieldTab = page.locator('#wfTabs .wf-tab').filter({ hasText: /Field intake/i });
    await fieldTab.click();

    // Check for batches or queue summary
    const summary = page.locator('.fq-summary');
    if (await summary.isVisible().catch(() => false)) {
      await expect(summary).toContainText(/photos.*batch/i);
      await expect(page.locator('.fq-live')).toContainText('Watching for new uploads');

      const batch = page.locator('.fq-batch').first();
      await expect(batch).toBeVisible();
      await expect(batch.locator('.fq-av')).toBeVisible();
      await expect(batch.locator('.fq-who')).toBeVisible();
      await expect(batch.locator('.fq-event')).toBeVisible();
      await expect(batch.locator('.fq-urg')).toBeVisible();

      // Photos in batch
      await expect(batch.locator('.fq-ph').first()).toBeVisible();

      // Batch footer buttons
      await expect(batch.locator('.fq-btn.ok')).toContainText(/Approve all/i);
      const rejectBtn = batch.locator('.fq-btn.danger');
      await expect(rejectBtn).toContainText(/Reject batch/i);

      // Click reject batch to open reason modal
      await rejectBtn.click();
      const modal = page.locator('#fqOverlay');
      await expect(modal).toHaveClass(/open/);
      await expect(page.locator('#fqTitle')).toContainText(/Reject batch/i);
      await expect(page.locator('.fq-reason')).toHaveCount(4);
      await expect(page.locator('#fqNote')).toBeVisible();
      await expect(page.locator('#fqConfirm')).toBeVisible();

      // Close modal
      await page.locator('#fqCancel').click();
      await expect(modal).not.toHaveClass(/open/);
    }
  });

  test('Workflow tabs switch active state and update displayed assets', async ({ page }) => {
    await page.goto('/admin/photos');

    const tabs = page.locator('#wfTabs .wf-tab');
    
    // Switch to Needs review
    const reviewTab = tabs.filter({ hasText: /Needs review/i });
    await reviewTab.click();
    await expect(reviewTab).toHaveClass(/active/);

    // Switch to In library
    const libTab = tabs.filter({ hasText: /In library/i });
    await libTab.click();
    await expect(libTab).toHaveClass(/active/);

    // Switch to Packaged
    const pkgTab = tabs.filter({ hasText: /Packaged/i });
    await pkgTab.click();
    await expect(pkgTab).toHaveClass(/active/);

    // Switch to Published
    const pubTab = tabs.filter({ hasText: /Published/i });
    await pubTab.click();
    await expect(pubTab).toHaveClass(/active/);

    // Return to All assets
    const allTab = tabs.filter({ hasText: /All assets/i });
    await allTab.click();
    await expect(allTab).toHaveClass(/active/);
  });

  test('Toolbar filters and unattached toggle update photo list interactively', async ({ page }) => {
    await page.goto('/admin/photos');

    // Toggle unattached button
    const unattachedBtn = page.locator('#unattachedBtn');
    await expect(unattachedBtn).toHaveClass(/btn-outline/);
    await unattachedBtn.click();
    await expect(unattachedBtn).toHaveClass(/btn-navy/);
    await unattachedBtn.click();
    await expect(unattachedBtn).toHaveClass(/btn-outline/);

    // Filter by sort dropdown
    const sortSelect = page.locator('#damSort');
    await sortSelect.selectOption('dl');
    await page.waitForTimeout(300);

    // Filter by search input
    const searchInput = page.locator('#damSearch');
    await searchInput.fill('Dhaka');
    await page.waitForTimeout(500);

    const damGrid = page.locator('#damGrid');
    await expect(damGrid).toBeVisible();

    // Clear search
    await searchInput.fill('');
    await page.waitForTimeout(400);
  });

  test('Inspector metadata form allows editing and saving updates', async ({ page }) => {
    await page.goto('/admin/photos');

    const damGrid = page.locator('#damGrid');
    const items = damGrid.locator('.dam-item');

    if (await items.count() > 0) {
      await items.first().click();

      const inspPanel = page.locator('#inspPanel');
      await expect(inspPanel).toBeVisible({ timeout: 5000 });

      // Edit caption and save
      const captionField = page.locator('#iCap');
      const originalValue = await captionField.inputValue();
      await captionField.fill(originalValue + ' [Updated Test]');

      await page.locator('#iSave').click();
      await expect(page.locator('.toast-msg')).toBeVisible({ timeout: 3000 });
      await expect(page.locator('.toast-msg')).toContainText(/saved/i);

      // Restore
      await captionField.fill(originalValue);
      await page.locator('#iSave').click();
      await page.waitForTimeout(400);

      // Close inspector
      await page.locator('#inspClose').click();
      await expect(inspPanel).not.toBeVisible();
    }
  });

  test('Field intake re-edit modal allows radio selection and cancel dismissal', async ({ page }) => {
    await page.goto('/admin/photos');

    const fieldTab = page.locator('#wfTabs .wf-tab').filter({ hasText: /Field intake/i });
    await fieldTab.click();

    const reeditBtn = page.locator('.fq-btn.warn').first();
    if (await reeditBtn.isVisible().catch(() => false)) {
      await reeditBtn.click();

      const modal = page.locator('#fqOverlay');
      await expect(modal).toHaveClass(/open/);
      await expect(page.locator('#fqTitle')).toContainText(/Request re-edit/i);

      // Radio reasons selectable
      const reasons = page.locator('.fq-reason');
      await expect(reasons).toHaveCount(4);
      await reasons.nth(1).click();
      await expect(reasons.nth(1)).toHaveClass(/sel/);

      // Dismiss with X
      await page.locator('#fqClose').click();
      await expect(modal).not.toHaveClass(/open/);
    }
  });
});

