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
});
