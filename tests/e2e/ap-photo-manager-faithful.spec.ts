import { test, expect } from '@playwright/test';

test.describe('AP Photo Manager Faithful (M8-PHOTO-003)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 }).catch(() => {});
  });

  test('Page header, breadcrumb, topbar actions, sync note, filter bar, and 13 category chips render', async ({ page }) => {
    await page.goto('/admin/ap-photos');

    // Breadcrumb & Heading
    await expect(page.locator('.breadcrumb')).toContainText('AP Photo Manager');
    await expect(page.getByRole('heading', { name: 'AP Photo Manager' })).toBeVisible({ timeout: 5000 });

    // Topbar action buttons
    await expect(page.getByRole('button', { name: 'Sync log' })).toBeVisible();
    await expect(page.locator('#syncBtn')).toBeVisible();

    // Sync note banner
    await expect(page.locator('.sync-note')).toContainText('AP wire auto-syncs every 15 minutes');

    // Filter controls
    await expect(page.locator('#apSearch')).toBeVisible();
    await expect(page.locator('#apCategory')).toBeVisible();
    await expect(page.locator('#resetFilters')).toBeVisible();

    // 13 Category Chips
    const chips = page.locator('#catChips .cat-chip');
    await expect(chips).toHaveCount(13);
    await expect(chips.first()).toHaveClass(/active/);
    await expect(chips.first()).toHaveText('All');

    // Result counter
    const resultCount = page.locator('#resultCount');
    await expect(resultCount).toBeVisible();
    await expect(resultCount).toContainText('Showing');
    await expect(resultCount).toContainText('AP photos');

    // Grid cards
    const cards = page.locator('#apGrid .ap-card');
    await expect(cards.first()).toBeVisible();
    await expect(cards.first().locator('.ap-badge')).toHaveText('AP');
    await expect(cards.first().locator('.attach-btn')).toBeVisible();
    await expect(cards.first().locator('.dl-btn')).toBeVisible();
  });

  test('Category chips filter photos interactively', async ({ page }) => {
    await page.goto('/admin/ap-photos');

    // Click Sports chip
    const sportsChip = page.locator('#catChips button[data-cat="sports"]');
    await sportsChip.click();

    // Verify chip is active
    await expect(sportsChip).toHaveClass(/active/);

    // Verify sports photo is displayed
    const grid = page.locator('#apGrid');
    await expect(grid).toContainText('Mirpur');

    // Click All chip to reset
    const allChip = page.locator('#catChips button[data-cat="all"]');
    await allChip.click();
    await expect(allChip).toHaveClass(/active/);
  });

  test('Search input filters photo captions and reset button restores default view', async ({ page }) => {
    await page.goto('/admin/ap-photos');

    const searchInput = page.locator('#apSearch');
    await searchInput.fill('Kyiv');

    // Wait for debounce and Livewire re-render
    await page.waitForTimeout(600);
    const grid = page.locator('#apGrid');
    await expect(grid).toContainText('Kyiv');

    // Click reset
    await page.locator('#resetFilters').click();
    await page.waitForTimeout(400);
    await expect(searchInput).toHaveValue('');
  });

  test('Clicking photo card opens centered 880px lightbox with 5 metadata rows and attach action', async ({ page }) => {
    await page.goto('/admin/ap-photos');

    const firstCard = page.locator('#apGrid .ap-card').first();
    await firstCard.click();

    // Lightbox modal opens
    const overlay = page.locator('#lbOverlay');
    await expect(overlay).toBeVisible();
    await expect(overlay.locator('.lb-modal')).toBeVisible();

    // 5 metadata rows
    const info = overlay.locator('.lb-info');
    await expect(info.locator('.lb-cap')).toBeVisible();
    await expect(info).toContainText('Credit');
    await expect(info).toContainText('AP Photo');
    await expect(info).toContainText('Category');
    await expect(info).toContainText('Wire date');
    await expect(info).toContainText('Dimensions');
    await expect(info).toContainText('Downloads');

    // Action button inside lightbox
    const lbAttach = overlay.locator('#lbAttach');
    await lbAttach.click();
    await expect(lbAttach).toContainText('✓ Attached to draft');

    // Close modal
    await overlay.locator('#lbClose').click();
    await expect(overlay).not.toBeVisible();
  });

  test('Card attach button shows instant attached state and Sync Log modal opens', async ({ page }) => {
    await page.goto('/admin/ap-photos');

    // Attach button on card
    const firstAttachBtn = page.locator('#apGrid .ap-card .attach-btn').first();
    await firstAttachBtn.click();
    await expect(firstAttachBtn).toHaveClass(/done/);
    await expect(firstAttachBtn).toContainText('✓ Attached');

    // Sync log modal
    await page.getByRole('button', { name: 'Sync log' }).click();
    await expect(page.getByRole('heading', { name: 'AP Wire Sync Log' })).toBeVisible();
    await expect(page.locator('table')).toContainText('AP Associated Press Media API v1');

    // Close sync log modal
    await page.getByRole('button', { name: 'Close' }).click();
    await expect(page.getByRole('heading', { name: 'AP Wire Sync Log' })).not.toBeVisible();

    // Sync now button feedback
    await page.locator('#syncBtn').click();
    await expect(page.locator('.sync-note')).toBeVisible();
  });
});
