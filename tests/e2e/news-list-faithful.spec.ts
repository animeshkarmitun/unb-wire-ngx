import { test, expect } from '@playwright/test';

test.describe('News List Faithful (M8-NEWS-001 & M8-NEWS-002)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 }).catch(() => {});
  });

  test('English News table renders 7 columns, category pills, views, and export button', async ({ page }) => {
    await page.goto('/admin/news/en');

    // Heading & Topbar
    await expect(page.getByRole('heading', { name: 'English News' })).toBeVisible({ timeout: 5000 });
    await expect(page.getByRole('button', { name: 'Export' })).toBeVisible();
    await expect(page.getByRole('link', { name: '+ Add News' })).toBeVisible();

    // Status filter tabs
    await expect(page.getByRole('button', { name: /All/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /Live/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /Draft/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /In review/i })).toBeVisible();

    // 7-column table headers
    const table = page.locator('.news-table');
    await expect(table).toBeVisible();
    await expect(table.locator('th')).toHaveCount(7);
    await expect(table.locator('th').nth(1)).toContainText('Title');
    await expect(table.locator('th').nth(2)).toContainText('Category');
    await expect(table.locator('th').nth(3)).toContainText('Sub category');
    await expect(table.locator('th').nth(4)).toContainText('Views');
    await expect(table.locator('th').nth(5)).toContainText('Status');
    await expect(table.locator('th').nth(6)).toContainText('Actions');

    // Check row elements if stories exist
    const rows = table.locator('tbody tr');
    const rowCount = await rows.count();
    if (rowCount > 0 && !await rows.first().locator('td[colspan]').isVisible().catch(() => false)) {
      // Thumbnail & title
      await expect(rows.first().locator('.thumb')).toBeVisible();
      await expect(rows.first().locator('.news-title')).toBeVisible();
      // Category pill
      await expect(rows.first().locator('.cat-tag')).toBeVisible();
      // Views with icon
      await expect(rows.first().locator('.views')).toBeVisible();
      // Actions
      await expect(rows.first().locator('.actions .icon-btn').first()).toBeVisible();
    }
  });

  test('Workflow drawer opens with stepper, owner, notes thread, and composer', async ({ page }) => {
    await page.goto('/admin/news/en');

    const table = page.locator('.news-table');
    const titleLink = table.locator('.news-title').first();

    if (await titleLink.isVisible().catch(() => false)) {
      await titleLink.click();

      // Drawer should slide open
      const drawer = page.locator('aside.wfd');
      await expect(drawer).toHaveClass(/open/, { timeout: 5000 });

      // Workflow stepper
      await expect(drawer.locator('.wfd-sec-label').first()).toContainText('Workflow');
      await expect(drawer.locator('.wfd-flow')).toBeVisible();
      await expect(drawer.locator('.wfd-step')).toHaveCount(4);

      // Owner card
      await expect(drawer.locator('.wfd-owner')).toBeVisible();
      await expect(drawer.locator('.wfd-owner .wf-ava')).toBeVisible();

      // Notes section & reply composer
      await expect(drawer.locator('.wfd-sec-label').nth(2)).toContainText('Internal notes');
      const textarea = drawer.locator('.nt-reply textarea');
      await expect(textarea).toBeVisible();
      const sendBtn = drawer.locator('.nt-reply button');
      await expect(sendBtn).toBeVisible();

      // Add a test reply note
      await textarea.fill('E2E automated test note response');
      await sendBtn.click();

      // Verify the new note appears in thread
      await expect(drawer.locator('.nt-list')).toContainText('E2E automated test note response', { timeout: 5000 });

      // Close drawer
      await drawer.locator('.wfd-close').click();
      await expect(drawer).not.toHaveClass(/open/);
    }
  });

  test('Bangla News surface renders faithfully with language scope', async ({ page }) => {
    await page.goto('/admin/news/bn');
    await expect(page.getByRole('heading', { name: 'Bangla News' })).toBeVisible({ timeout: 5000 });
    await expect(page.locator('.news-table')).toBeVisible();
  });

  test('Status filter tabs cycle and update active state correctly', async ({ page }) => {
    await page.goto('/admin/news/en');
    await expect(page.getByRole('heading', { name: 'English News' })).toBeVisible({ timeout: 5000 });

    const statusTabs = [
      { name: /Live/i, value: 'published' },
      { name: /Draft/i, value: 'draft' },
      { name: /In review/i, value: 'in_review' },
      { name: /Needs work/i, value: 'changes_requested' },
      { name: /All/i, value: 'all' },
    ];

    for (const tab of statusTabs) {
      const tabButton = page.getByRole('button', { name: tab.name }).first();
      await expect(tabButton).toBeVisible();
      await tabButton.click();
      // Verify active background class
      await expect(tabButton).toHaveClass(/bg-navy-800/);
    }
  });

  test('Search input and category filter filter table rows dynamically', async ({ page }) => {
    await page.goto('/admin/news/en');
    await expect(page.getByRole('heading', { name: 'English News' })).toBeVisible({ timeout: 5000 });

    // Search input
    const searchInput = page.locator('input.search-input');
    await expect(searchInput).toBeVisible();
    await searchInput.fill('NonexistentQueryX123999');
    
    // Check empty state appears or row count drops
    await expect(page.locator('.news-table tbody')).toContainText('No stories found', { timeout: 7000 });

    // Clear search
    await searchInput.fill('');
    await page.waitForTimeout(500);

    // Category dropdown filter
    const catSelect = page.locator('select.filter-select').first();
    await expect(catSelect).toBeVisible();
    const options = await catSelect.locator('option').allInnerTexts();
    if (options.length > 1) {
      await catSelect.selectOption({ index: 1 });
      await page.waitForTimeout(400);
      // Verify table is still rendered
      await expect(page.locator('.news-table')).toBeVisible();
    }
  });

  test('Multi-select row checkboxes toggle bulk action bar and actions', async ({ page }) => {
    await page.goto('/admin/news/en');
    await expect(page.getByRole('heading', { name: 'English News' })).toBeVisible({ timeout: 5000 });

    const rows = page.locator('.news-table tbody tr');
    const rowCount = await rows.count();

    if (rowCount > 0 && !await rows.first().locator('td[colspan]').isVisible().catch(() => false)) {
      const firstRowCheckbox = rows.first().locator('td').first().locator('input[type="checkbox"]');
      await expect(firstRowCheckbox).toBeVisible();

      // Bulk bar should not be visible before selection
      await expect(page.locator('text=stories selected')).not.toBeVisible();

      // Select first row
      await firstRowCheckbox.check();
      
      // Bulk bar should become visible
      const bulkBar = page.locator('.bg-navy-800.text-white:has-text("selected")');
      await expect(bulkBar).toBeVisible({ timeout: 5000 });
      await expect(bulkBar).toContainText('1 story selected');
      await expect(bulkBar.getByRole('button', { name: 'Publish Selected' })).toBeVisible();
      await expect(bulkBar.getByRole('button', { name: 'Delete Selected' })).toBeVisible();

      // Select all header checkbox
      const selectAllCheckbox = page.locator('thead input[type="checkbox"]');
      await selectAllCheckbox.check();
      await expect(bulkBar).toContainText(/selected/);

      // Uncheck select all
      await selectAllCheckbox.uncheck();
      await expect(bulkBar).not.toBeVisible({ timeout: 5000 });
    }
  });

  test('Row navigation links and live toggle switches function properly', async ({ page }) => {
    test.setTimeout(60000);
    await page.goto('/admin/news/en');
    await expect(page.getByRole('heading', { name: 'English News' })).toBeVisible({ timeout: 5000 });

    // "+ Add News" button
    const addBtn = page.getByRole('link', { name: '+ Add News' });
    await expect(addBtn).toHaveAttribute('href', /.*add-news.*/);

    const rows = page.locator('.news-table tbody tr');
    if (await rows.count() > 0 && !await rows.first().locator('td[colspan]').isVisible().catch(() => false)) {
      // Story view reader link
      const readerLink = rows.first().locator('.actions a.story-view-link');
      if (await readerLink.isVisible().catch(() => false)) {
        await expect(readerLink).toHaveAttribute('href', /.*admin\/story\/.*/);
      }

      // Edit story link
      const editLink = rows.first().locator('.actions a[title="Edit story"]');
      if (await editLink.isVisible().catch(() => false)) {
        await expect(editLink).toHaveAttribute('href', /.*add-news\?id=.*/);
      }

      // Live switch toggle
      const switchLabel = rows.first().locator('label.switch');
      if (await switchLabel.isVisible().catch(() => false)) {
        await switchLabel.click();
        // Allow Livewire roundtrip
        await page.waitForTimeout(1000);
      }
    }
  });
});

