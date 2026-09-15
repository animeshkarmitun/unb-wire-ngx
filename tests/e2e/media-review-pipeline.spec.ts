import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { execSync } from 'child_process';

function seedMedia() {
  execSync('php tests/e2e/helpers/seed-data.php media', { stdio: 'ignore' });
}

function queryDb(tinkerExpr: string): string {
  return execSync(
    `php artisan tinker --execute="${tinkerExpr}"`,
    { encoding: 'utf-8' }
  ).trim();
}

test.describe('Media Review & Field Intake Queue Pipeline (M9-MED / COS-4 / COS-8)', () => {
  test.beforeEach(async () => {
    try {
      seedMedia();
    } catch (_) {}
  });

  test('Field Intake Queue renders pending batches, urgency badge, photos, and uploader meta', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/photos?tab=field');
    await page.waitForLoadState('networkidle');

    // Summary banner
    const summary = page.locator('.fq-summary');
    await expect(summary).toBeVisible({ timeout: 10000 });
    await expect(summary).toContainText('photos in');
    await expect(summary).toContainText('waiting for desk review');

    // Batch card
    const batch = page.locator('.fq-batch', { hasText: 'Sylhet flood relief' }).first();
    await expect(batch).toBeVisible();
    await expect(batch.locator('.fq-who')).toContainText('Mim Akter');
    await expect(batch.locator('.fq-event')).toContainText('Sylhet flood relief field photos');
    await expect(batch.locator('.fq-urg')).toContainText('urgent');
    await expect(batch.locator('.fq-wait')).toContainText('2 frames');

    // Photo items inside batch
    const photos = batch.locator('.fq-ph');
    await expect(photos).toHaveCount(2);
    await expect(photos.first().locator('.fq-cap')).toContainText('Volunteers distribute drinking water');
    await expect(photos.nth(1).locator('.fq-cap')).toContainText('Villagers take refuge on an elevated road');

    // Batch action buttons
    const bfoot = batch.locator('.fq-bfoot');
    await expect(bfoot.locator('.fq-btn.ok')).toContainText('Approve all 2');
    await expect(bfoot.locator('.fq-btn.warn')).toContainText('Request re-edit…');
    await expect(bfoot.locator('.fq-btn.danger')).toContainText('Reject batch…');
  });

  test('Desk Editor approves single photo from field intake queue', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/photos?tab=field');
    await page.waitForLoadState('networkidle');

    const batch = page.locator('.fq-batch', { hasText: 'Sylhet flood relief' }).first();
    await expect(batch).toBeVisible();

    const firstPhoto = batch.locator('.fq-ph').first();
    await expect(firstPhoto).toBeVisible();

    // Hover over photo to reveal hover action buttons, then click approve
    await firstPhoto.hover();
    const approveBtn = firstPhoto.locator('.fq-pact.ok');
    await expect(approveBtn).toBeVisible();
    await approveBtn.click();

    // Toast notification confirms approval and photographer notification
    await expect(page.locator('.toast-msg').getByText(/Approved to library — Mim Akter notified/i)).toBeVisible({ timeout: 5000 });

    // Queue updates: batch now has 1 frame remaining
    await expect(batch.locator('.fq-wait')).toContainText('1 frame');
    await expect(batch.locator('.fq-ph')).toHaveCount(1);

    // Verify DB state
    const assetStatus = queryDb("echo \\App\\Models\\MediaAsset::where('public_id', '01JMEDIAASSET0000000001')->value('status');");
    expect(assetStatus).toBe('library');

    const reviewAction = queryDb("echo \\App\\Models\\MediaReview::where('asset_id', \\App\\Models\\MediaAsset::where('public_id', '01JMEDIAASSET0000000001')->value('id'))->value('action');");
    expect(reviewAction).toBe('approve');
  });

  test('Desk Editor rejects single photo with reason code and custom note', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/photos?tab=field');
    await page.waitForLoadState('networkidle');

    const batch = page.locator('.fq-batch', { hasText: 'Sylhet flood relief' }).first();
    await expect(batch).toBeVisible();

    const firstPhoto = batch.locator('.fq-ph').first();
    await expect(firstPhoto).toBeVisible();

    // Hover and click reject
    await firstPhoto.hover();
    const rejectBtn = firstPhoto.locator('.fq-pact.no');
    await expect(rejectBtn).toBeVisible();
    await rejectBtn.click();

    // Modal opens
    const modal = page.locator('#fqOverlay');
    await expect(modal).toBeVisible({ timeout: 5000 });
    await expect(page.locator('#fqTitle')).toContainText('Reject photo');

    // Select reason
    const reasonLabel = page.locator('.fq-reason', { hasText: 'Weak composition — not publishable' });
    await reasonLabel.click();

    // Type optional note
    await page.locator('#fqNote').fill('Subject is obscured by background glare and crowd');

    // Confirm
    await page.locator('#fqConfirm').click();

    // Modal closes and toast confirms
    await expect(modal).not.toBeVisible();
    await expect(page.locator('.toast-msg').getByText(/Photo rejected — Mim Akter notified with reason/i)).toBeVisible({ timeout: 5000 });

    // Verify DB state
    const assetStatus = queryDb("echo \\App\\Models\\MediaAsset::where('public_id', '01JMEDIAASSET0000000001')->value('status');");
    expect(assetStatus).toBe('rejected');

    const reviewCode = queryDb("echo \\App\\Models\\MediaReview::where('asset_id', \\App\\Models\\MediaAsset::where('public_id', '01JMEDIAASSET0000000001')->value('id'))->value('reason_code');");
    expect(reviewCode).toBe('weak-composition-not-publishable');
  });

  test('Desk Editor requests re-edit for batch with specific editorial reason', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/photos?tab=field');
    await page.waitForLoadState('networkidle');

    const batch = page.locator('.fq-batch', { hasText: 'Sylhet flood relief' }).first();
    await expect(batch).toBeVisible();

    // Click request re-edit on batch
    const reeditBtn = batch.locator('.fq-bfoot .fq-btn.warn');
    await expect(reeditBtn).toBeVisible();
    await reeditBtn.click();

    // Modal opens with batch title
    const modal = page.locator('#fqOverlay');
    await expect(modal).toBeVisible({ timeout: 5000 });
    await expect(page.locator('#fqTitle')).toContainText('Request re-edit — Sylhet flood relief field photos');

    // Select re-edit reason
    const reasonLabel = page.locator('.fq-reason', { hasText: 'Captions need names / places filled in' });
    await reasonLabel.click();

    // Fill note
    await page.locator('#fqNote').fill('Please identify the local administration officer in frame 1');

    // Confirm
    await page.locator('#fqConfirm').click();

    // Toast confirms
    await expect(modal).not.toBeVisible();
    await expect(page.locator('.toast-msg').getByText(/Sent back — Mim Akter asked to fix and resend/i)).toBeVisible({ timeout: 5000 });

    // Seeded Sylhet batch is no longer in pending queue
    await expect(page.locator('.fq-batch', { hasText: 'Sylhet flood relief' })).toHaveCount(0);

    // Verify DB batch and assets
    const batchStatus = queryDb("echo \\App\\Models\\MediaBatch::where('public_id', '01JMEDIABATCH0000000001')->value('status');");
    expect(batchStatus).toBe('reviewed');

    const asset1Status = queryDb("echo \\App\\Models\\MediaAsset::where('public_id', '01JMEDIAASSET0000000001')->value('status');");
    expect(asset1Status).toBe('reedit');

    const reviewAction = queryDb("echo \\App\\Models\\MediaReview::where('asset_id', \\App\\Models\\MediaAsset::where('public_id', '01JMEDIAASSET0000000001')->value('id'))->value('action');");
    expect(reviewAction).toBe('reedit');
  });

  test('Desk Editor approves entire batch at once', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/photos?tab=field');
    await page.waitForLoadState('networkidle');

    const batch = page.locator('.fq-batch', { hasText: 'Sylhet flood relief' }).first();
    await expect(batch).toBeVisible();

    // Click Approve all frames
    const approveAllBtn = batch.locator('.fq-bfoot .fq-btn.ok');
    await expect(approveAllBtn).toBeVisible();
    await approveAllBtn.click();

    // Toast confirms
    await expect(page.locator('.toast-msg').getByText(/2 frames approved to library — Mim Akter notified/i)).toBeVisible({ timeout: 5000 });

    // Seeded Sylhet batch is no longer in pending queue
    await expect(page.locator('.fq-batch', { hasText: 'Sylhet flood relief' })).toHaveCount(0);

    // Verify DB
    const batchStatus = queryDb("echo \\App\\Models\\MediaBatch::where('public_id', '01JMEDIABATCH0000000001')->value('status');");
    expect(batchStatus).toBe('reviewed');

    const asset1Status = queryDb("echo \\App\\Models\\MediaAsset::where('public_id', '01JMEDIAASSET0000000001')->value('status');");
    const asset2Status = queryDb("echo \\App\\Models\\MediaAsset::where('public_id', '01JMEDIAASSET0000000002')->value('status');");
    expect(asset1Status).toBe('library');
    expect(asset2Status).toBe('library');
  });

  test('Sticky Inspector allows metadata editing, package assignment, and persistence', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/photos?tab=library');
    await page.waitForLoadState('networkidle');

    // Find the library photo seeded: BAPA press conference
    const photoItem = page.locator('.dam-item', { hasText: 'BAPA press conference' }).first();
    await expect(photoItem).toBeVisible({ timeout: 10000 });
    await photoItem.click();

    // Sticky inspector panel opens
    const inspector = page.locator('#inspPanel');
    await expect(inspector).toBeVisible({ timeout: 5000 });

    // Edit caption, location, keywords, and package
    await page.locator('#iCap').fill('Updated Buriganga River water quality conference');
    await page.locator('#iLoc').fill('Old Dhaka');
    await page.locator('#iKw').fill('water, buriganga, environment, bapa');
    await page.locator('#iPkg').selectOption('Exclusive');

    // Save changes
    await page.locator('#iSave').click();

    // Toast confirms
    await expect(page.locator('.toast-msg').getByText(/✓ Metadata saved/i)).toBeVisible({ timeout: 5000 });

    // Verify DB persistence
    const updatedCaption = queryDb("echo \\App\\Models\\MediaAsset::where('public_id', '01JMEDIALIBASSET00000001')->value('caption');");
    expect(updatedCaption).toContain('Updated Buriganga River');

    const updatedLocation = queryDb("echo \\App\\Models\\MediaAsset::where('public_id', '01JMEDIALIBASSET00000001')->value('location_city');");
    expect(updatedLocation).toBe('Old Dhaka');

    const hasExclusivePkg = queryDb("echo \\App\\Models\\MediaAsset::where('public_id', '01JMEDIALIBASSET00000001')->first()->packages()->where('code', 'PREMIUM-BUNDLE')->exists() ? 'yes' : 'no';");
    expect(hasExclusivePkg).toBe('yes');
  });

  test('Bulk selection bar enables multi-select, package assignment, and clearing', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/photos?tab=library');
    await page.waitForLoadState('networkidle');

    const bulkbar = page.locator('#bulkbar');
    await expect(bulkbar).not.toHaveClass(/show/);

    // Select first checkbox
    const firstCheck = page.locator('.dam-item input.dam-check').first();
    await firstCheck.click();

    // Bulk bar appears
    await expect(bulkbar).toHaveClass(/show/, { timeout: 5000 });
    await expect(page.locator('#bulkCount')).toContainText('1 selected');

    // Assign package via bulk dropdown
    await page.locator('#bulkPkg').selectOption('Standard');

    // Toast confirms
    await expect(page.locator('.toast-msg').getByText(/assigned to Standard package/i)).toBeVisible({ timeout: 5000 });

    // Clear selection
    await page.locator('#bulkClear').click();
    await expect(bulkbar).not.toHaveClass(/show/);
  });

  test('Field photographer receives desk review notification in notification center', async ({ page }) => {
    // Approve batch as admin first to generate notification
    await loginAs(page, 'admin');
    await page.goto('/admin/photos?tab=field');
    await page.waitForLoadState('networkidle');

    const batch = page.locator('.fq-batch', { hasText: 'Sylhet flood relief' }).first();
    await expect(batch).toBeVisible();

    const approveAllBtn = batch.locator('.fq-bfoot .fq-btn.ok');
    await expect(approveAllBtn).toBeVisible();
    await approveAllBtn.click();
    await expect(page.locator('.toast-msg').getByText(/approved to library/i)).toBeVisible({ timeout: 5000 });

    // Log in as photographer Mim Akter
    await page.context().clearCookies();
    await loginAs(page, 'photographer');
    await page.goto('/admin/notifications');
    await page.waitForLoadState('networkidle');

    // Verify notification appears
    const notifItem = page.locator('main').getByText('Media approved').first();
    await expect(notifItem).toBeVisible({ timeout: 10000 });

    // Click notification card — verifies deep link directs to /admin/photos
    await notifItem.click();
    await page.waitForURL('**/admin/photos**', { timeout: 5000 });
    await expect(page).toHaveURL(/\/admin\/photos/);
  });
});
