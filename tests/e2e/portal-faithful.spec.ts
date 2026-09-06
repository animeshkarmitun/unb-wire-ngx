import { test, expect } from '@playwright/test';

test.describe('Client Portal Faithful Prototype Parity (app-data/client-portal.html)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://localhost:3000/');
    await expect(page.locator('.mast-name')).toContainText('UNB Wire', { timeout: 15000 });
  });

  test('Masthead renders brand strip, live clock, and user dropdown with quota meters', async ({ page }) => {
    // Brand strip
    await expect(page.locator('.brand-strip')).toBeVisible();

    // Clock
    await expect(page.locator('#clockTime')).toContainText(/Dhaka/i);
    await expect(page.locator('.live-dot').first()).toBeVisible();

    // Newsroom link
    const adminLink = page.locator('a.admin-chip');
    await expect(adminLink).toBeVisible();
    await expect(adminLink).toHaveAttribute('href', 'http://localhost:8000/admin');

    // User dropdown
    const userBtn = page.locator('#userBtn');
    await expect(userBtn).toBeVisible();
    await expect(userBtn).toContainText('Daily Star');
    await expect(userBtn).toContainText('Premium subscriber');

    // Open dropdown
    await userBtn.click();
    const dropdown = page.locator('#userDrop');
    await expect(dropdown).toHaveClass(/open/);
    await expect(dropdown).toContainText('Premium · renews 1 Oct 2026');
    await expect(dropdown).toContainText('342/500');

    // Close dropdown on outside click
    await page.locator('.brand-mark').click();
    await expect(dropdown).not.toHaveClass(/open/);
  });

  test('Collapsible left rail toggles on #railToggle click with filters and saved searches', async ({ page }) => {
    const pageContainer = page.locator('.page');
    // Initially rail is collapsed
    await expect(pageContainer).toHaveClass(/rail-hidden/);

    // Click rail toggle button
    const railToggle = page.locator('#railToggle');
    await railToggle.click();
    await expect(pageContainer).not.toHaveClass(/rail-hidden/);
    await expect(railToggle).toHaveClass(/active/);

    // Verify aside contents
    await expect(page.locator('.sub-name')).toHaveText('Daily Star');
    await expect(page.locator('#mediaQuotaLabel')).toContainText(/87 \/ 150/);

    // Check saved search click
    const electionSavedSearch = page.locator('.saved-item[data-q="election"]');
    await expect(electionSavedSearch).toBeVisible();
    await electionSavedSearch.click();

    const omniInput = page.locator('#omniInput');
    await expect(omniInput).toHaveValue('election');

    // Clear filters
    const clearBtn = page.locator('#clearFilters');
    await clearBtn.click();
  });

  test('Omnisearch filters stories and supports / shortcut', async ({ page }) => {
    const omniInput = page.locator('#omniInput');
    await expect(omniInput).toBeVisible();

    // Press '/' key while outside inputs to trigger shortcut focus
    await page.keyboard.press('Escape');
    await page.keyboard.press('/');
    await expect(omniInput).toBeFocused();

    // Search for specific story keyword
    await omniInput.fill('Swapon');
    await expect(page.locator('#searchMeta')).toBeVisible();
    await expect(page.locator('#searchMeta')).toContainText(/result/i);

    // Verify matching story displayed
    await expect(page.locator('text=Swapon joins as PM\'s political adviser')).toBeVisible();
  });

  test('Wire view switcher alternates between Cards, Grid, and Table views', async ({ page }) => {
    // 1. Default: Cards view
    await expect(page.locator('#feedList')).toBeVisible();
    await expect(page.locator('#feedGrid')).toBeHidden();
    await expect(page.locator('#feedTable')).toBeHidden();

    // Test inline dispatch expander
    const firstPreviewBtn = page.locator('#feedList .st-btn.preview').first();
    await firstPreviewBtn.click();
    await expect(page.locator('#feedList .st-body.open').first()).toBeVisible();

    // 2. Switch to Grid view
    const gridBtn = page.locator('#viewSwitch .view-btn[data-view="grid"]');
    await gridBtn.click();
    await expect(page.locator('#feedGrid')).toBeVisible();
    await expect(page.locator('#feedList')).toBeHidden();
    await expect(page.locator('#feedGrid .wire-grid')).toBeVisible();

    // Test grid read/expand
    const firstReadBtn = page.locator('#feedGrid .wg-read').first();
    await firstReadBtn.click();
    await expect(page.locator('#feedGrid .wg-card.open').first()).toBeVisible();

    // 3. Switch to Table view (Terminal)
    const tableBtn = page.locator('#viewSwitch .view-btn[data-view="table"]');
    await tableBtn.click();
    await expect(page.locator('#feedTable')).toBeVisible();
    await expect(page.locator('#feedGrid')).toBeHidden();
    await expect(page.locator('#feedTable table.tw')).toBeVisible();

    // Test density toggle in table
    const compactBtn = page.locator('#feedTable .tw-density button:has-text("Compact")');
    await compactBtn.click();
    await expect(page.locator('.tw-wrap')).toHaveClass(/tw-compact/);
  });

  test('Bulk selection shows action bar with export options', async ({ page }) => {
    const firstStoryCheck = page.locator('#feedList .st-check input[type="checkbox"]').first();
    await firstStoryCheck.check();

    const bulkBar = page.locator('#bulkBar');
    await expect(bulkBar).toHaveClass(/show/);
    await expect(page.locator('#bulkCount')).toContainText('1 selected');

    // Test Word and CSV buttons exist
    await expect(page.locator('#bulkWord')).toBeVisible();
    await expect(page.locator('#bulkXml')).toBeVisible();
    await expect(page.locator('#bulkCsv')).toBeVisible();

    // Clear selection
    await page.locator('#bulkClear').click();
    await expect(bulkBar).not.toHaveClass(/show/);
  });

  test('Media Library tab switches panels and opens Asset Detail Modal', async ({ page }) => {
    const packsTab = page.locator('.feed-tab[data-tab="packs"]');
    await packsTab.click();

    await expect(page.locator('#packsPanel')).toBeVisible();
    await expect(page.locator('#wirePanel')).toBeHidden();

    // Source filters
    const apChip = page.locator('#srcChips .src-chip[data-src="ap"]');
    await apChip.click();
    await expect(apChip).toHaveClass(/active/);

    // Open first media card
    const firstCard = page.locator('#mediaGrid .ml-card').first();
    await firstCard.click();

    // Asset Detail Modal
    const modal = page.locator('#amOverlay');
    await expect(modal).toHaveClass(/open/);
    await expect(page.locator('#amCap')).toBeVisible();
    await expect(page.locator('#amCopyCredit')).toBeVisible();

    // Close modal via Esc key
    await page.keyboard.press('Escape');
    await expect(modal).not.toBeVisible();
  });

  test('UNB Photos showcase tab renders Photo of the Day hero, photo stories, and collections', async ({ page }) => {
    const photosTab = page.locator('.feed-tab[data-tab="photos"]');
    await photosTab.click();

    await expect(page.locator('#photosPanel')).toBeVisible();
    await expect(page.locator('.ph-hero')).toBeVisible();
    await expect(page.locator('.ph-hero-tag')).toHaveText('★ Photo of the day');

    // Photo stories
    await expect(page.locator('#galRow .gal-card')).toHaveCount(3);
    const firstBrowseBtn = page.locator('#galRow [data-gal-browse="0"]');
    await expect(firstBrowseBtn).toBeVisible();

    // Collections
    await expect(page.locator('#collRow .coll-card')).toHaveCount(4);
    const firstBell = page.locator('#collRow .coll-bell').first();
    await firstBell.click();
    await expect(firstBell).toHaveClass(/on/);

    // Trending list
    await expect(page.locator('#trendList .trend-item')).toHaveCount(5);
  });

  test('Photo Lightbox opens from photo story and navigates frames', async ({ page }) => {
    const photosTab = page.locator('.feed-tab[data-tab="photos"]');
    await photosTab.click();

    // Click browse on first gallery
    const firstBrowseBtn = page.locator('#galRow [data-gal-browse="0"]');
    await firstBrowseBtn.click();

    const lb = page.locator('#lightbox');
    await expect(lb).toHaveClass(/open/);
    await expect(page.locator('#lbCount')).toContainText('1 / 4');

    // Navigate next
    await page.locator('#lbNext').click();
    await expect(page.locator('#lbCount')).toContainText('2 / 4');

    // Close via close button
    await page.locator('#lbClose').click();
    await expect(lb).not.toBeVisible();
  });
});
