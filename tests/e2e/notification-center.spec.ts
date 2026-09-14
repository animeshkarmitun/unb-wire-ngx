import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { loginAs } from './helpers/auth';

test.describe('Editorial Notifications & Notification Center (M12-NTF)', () => {
  test.setTimeout(45000);

  test.beforeEach(async ({ page }) => {
    try {
      execSync('php tests/e2e/helpers/seed-data.php notifications', { stdio: 'ignore' });
    } catch (_) {}

    await loginAs(page, 'admin');
  });

  test('Topnav displays notification bell with unread badge counter and opens dropdown', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    // Bell button with unread count badge
    const bellBtn = page.locator('button[aria-label="Notifications"]');
    await expect(bellBtn).toBeVisible({ timeout: 5000 });

    const badge = bellBtn.locator('span.bg-crimson');
    await expect(badge).toBeVisible();
    await expect(badge).toContainText('2');

    // Click bell to open notifications dropdown
    await bellBtn.click();

    const dropdown = page.locator('div[x-show="open"]:has-text("Notifications")');
    await expect(dropdown).toBeVisible();
    await expect(dropdown).toContainText('2 new');
    await expect(dropdown).toContainText('Metro rail expansion phase 2 approved');
    await expect(dropdown).toContainText('Central Bank announces export incentives');

    // "View all notifications" link should be present and point to /admin/notifications
    const viewAllLink = dropdown.locator('a:has-text("View all notifications")');
    await expect(viewAllLink).toBeVisible();
    await viewAllLink.click();
    await page.waitForURL('**/admin/notifications', { timeout: 8000 });
    expect(page.url()).toContain('/admin/notifications');
  });

  test('Notification Center page renders notifications and allows filtering by unread', async ({ page }) => {
    await page.goto('/admin/notifications');
    await page.waitForLoadState('networkidle');

    // Header and description
    await expect(page.locator('main h1')).toHaveText('Notifications');
    await expect(page.locator('main').getByText('Editorial alerts, review requests, and workflow updates')).toBeVisible();

    // Filter tabs
    const allTab = page.locator('main button:has-text("All")').first();
    const unreadTab = page.locator('main button:has-text("Unread")').first();
    await expect(allTab).toBeVisible();
    await expect(unreadTab).toBeVisible();

    // Both seeded notifications should be visible in main
    await expect(page.locator('main').locator('text=Story sent for review')).toBeVisible();
    await expect(page.locator('main').locator('text=Story approved')).toBeVisible();

    // Switch to Unread filter
    await unreadTab.click();
    await expect(page.locator('main').locator('text=Metro rail expansion phase 2 approved')).toBeVisible();
  });

  test('Marking single notification as read updates read state', async ({ page }) => {
    await page.goto('/admin/notifications');
    await page.waitForLoadState('networkidle');

    // Initially 2 unread indicators in main list
    const unreadDots = page.locator('main span[title="Unread"]');
    await expect(unreadDots).toHaveCount(2);

    // Click first notification to mark as read
    const firstNotif = page.locator('main a:has-text("Metro rail expansion phase 2 approved")').first();
    await firstNotif.click();

    // Re-visit notifications to check read status
    await page.goto('/admin/notifications');
    await page.waitForLoadState('networkidle');

    // Now only 1 unread indicator should remain
    await expect(page.locator('main span[title="Unread"]')).toHaveCount(1);
  });

  test('Mark all as read clears all unread notifications and badge', async ({ page }) => {
    await page.goto('/admin/notifications');
    await page.waitForLoadState('networkidle');

    const markAllBtn = page.locator('main button:has-text("Mark all as read")');
    await expect(markAllBtn).toBeVisible();
    await markAllBtn.click();

    // Wait for Livewire to process
    await expect(markAllBtn).not.toBeVisible({ timeout: 5000 });
    await expect(page.locator('main span[title="Unread"]')).toHaveCount(0);

    // Reload page to reflect updated topnav state
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Check topnav bell badge is removed
    const bellBadge = page.locator('button[aria-label="Notifications"] span.bg-crimson');
    await expect(bellBadge).not.toBeVisible();
  });
});
