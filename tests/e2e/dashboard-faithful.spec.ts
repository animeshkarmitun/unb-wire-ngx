import { test, expect } from '@playwright/test';

test.describe('Dashboard Faithful (M8-DASH-001)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 8000 }).catch(() => {});
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('KPI cards render with values and delta indicators', async ({ page }) => {
    await page.goto('/admin');
    await expect(page.locator('h1')).toContainText('Dashboard');

    // 4 KPI labels
    await expect(page.locator('text=Stories published today')).toBeVisible();
    await expect(page.locator('text=Active clients')).toBeVisible();
    await expect(page.locator('text=Distribution success rate')).toBeVisible();
    await expect(page.locator('text=Exclusive content sent')).toBeVisible();

    // Delta indicators
    await expect(page.locator('text=/yesterday|this week|from last week/i').first()).toBeVisible();
  });

  test('Recent stories table renders with real route links and distribution bar', async ({ page }) => {
    await page.goto('/admin');
    await expect(page.getByText('Recent stories', { exact: true })).toBeVisible();
    await expect(page.locator('text=Story').first()).toBeVisible();
    await expect(page.locator('text=Distribution').first()).toBeVisible();

    // "View all" link goes to English News
    const viewAllLink = page.getByRole('link', { name: 'View all' });
    await expect(viewAllLink).toBeVisible();
    await expect(viewAllLink).toHaveAttribute('href', /admin\/news\/en/);
  });

  test('Top clients panel renders with All clients link', async ({ page }) => {
    await page.goto('/admin');
    await expect(page.getByText('Top clients today', { exact: true })).toBeVisible();

    // "All clients" link goes to Clients manager
    const allClientsLink = page.getByRole('link', { name: 'All clients' });
    await expect(allClientsLink).toBeVisible();
    await expect(allClientsLink).toHaveAttribute('href', /admin\/clients/);
  });

  test('Top bar actions: Export report triggers toast and + New story navigates to wizard', async ({ page }) => {
    await page.goto('/admin');

    // Export report toast
    const exportBtn = page.getByRole('button', { name: 'Export report' });
    await expect(exportBtn).toBeVisible();
    await exportBtn.click();
    await expect(page.locator('#toastWrap .toast, .toast').filter({ hasText: 'Export report initiated' })).toBeVisible({ timeout: 5000 });

    // + New story link
    const newStoryBtn = page.getByRole('link', { name: '+ New story' });
    await expect(newStoryBtn).toBeVisible();
    await newStoryBtn.click();
    await page.waitForURL('**/admin/add-news', { timeout: 8000 });
    expect(page.url()).toContain('/admin/add-news');
  });

  test('Recent stories table allows navigating to individual story reader', async ({ page }) => {
    await page.goto('/admin');
    
    const storyLinks = page.locator('section a[href*="/admin/story/"]');
    if (await storyLinks.count() > 0) {
      const firstLink = storyLinks.first();
      await firstLink.click();
      await page.waitForURL('**/admin/story/**', { timeout: 8000 });
      expect(page.url()).toContain('/admin/story/');
    }
  });

  test('Download FAB is visible and interactive', async ({ page }) => {
    await page.goto('/admin');
    const fab = page.locator('button[title="Download"]');
    await expect(fab).toBeVisible();
    await fab.click();
    await expect(page.locator('#toastWrap .toast, .toast').filter({ hasText: 'Download initiated' })).toBeVisible({ timeout: 5000 });
  });
});

