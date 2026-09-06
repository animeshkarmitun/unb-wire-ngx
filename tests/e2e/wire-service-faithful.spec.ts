import { test, expect } from '@playwright/test';

test.describe('Wire Service Frontpage Faithful (M8-SERV-001 / english-service.html + bn parity)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 }).catch(() => {});
  });

  test('Navigates to English Wire Service frontpage and verifies faithful prototype structure', async ({ page }) => {
    await page.goto('/admin/service/en');

    const serviceWrap = page.locator('.wire-service-wrap');
    await expect(serviceWrap).toBeVisible({ timeout: 10000 });

    // 1. Brand gradient strip
    await expect(page.locator('.wire-service-wrap .brand-strip')).toBeVisible();

    // 2. Masthead
    const masthead = page.locator('.wire-service-wrap .masthead');
    await expect(masthead).toBeVisible();
    await expect(masthead.locator('.brand-mark')).toHaveText('U');
    await expect(masthead.locator('.mast-name')).toHaveText('UNB');
    await expect(masthead.locator('.mast-tag')).toContainText('English Service');
    await expect(page.locator('#wsClockTime')).toContainText(/Dhaka/i);
    await expect(page.locator('.mast-search input')).toBeVisible();
    await expect(page.locator('a.admin-chip[href*="/admin/add-news"]')).toBeVisible();

    // 3. Category navigation bar
    const catNav = page.locator('#wsCatNav');
    await expect(catNav).toBeVisible();
    const allBtn = catNav.locator('button').first();
    await expect(allBtn).toHaveClass(/active/);
    await expect(allBtn).toContainText(/All/i);

    // 4. Breaking / News ticker
    const ticker = page.locator('.ticker-wrap .ticker');
    await expect(ticker).toBeVisible();
    await expect(ticker.locator('.ticker-label')).toContainText(/News Updates/i);
    await expect(ticker.locator('.ticker-label .pulse')).toBeVisible();
    await expect(page.locator('#wsTickerText')).toBeVisible();

    // 5. Home grid layout (left main + right rail)
    await expect(page.locator('.home-grid')).toBeVisible();

    // Hero story
    const heroStory = page.locator('.hero');
    if (await heroStory.count() > 0) {
      await expect(heroStory).toBeVisible();
      await expect(heroStory.locator('.hero-img')).toBeVisible();
      await expect(heroStory.locator('.hero-overlay')).toBeVisible();
      await expect(heroStory.locator('.hero-cat')).toBeVisible();
      await expect(heroStory.locator('.hero-head')).toBeVisible();
      await expect(heroStory.locator('.hero-meta')).toBeVisible();
    }

    // Right Rail
    const rail = page.locator('.rail');
    await expect(rail).toBeVisible();
    await expect(rail.locator('.rail-tabs')).toBeVisible();
    await expect(rail.locator('.rail-tab.active')).toContainText('Latest');
    await expect(rail.locator('.rail-item').first()).toBeVisible();

    // 6. Footer
    const footer = page.locator('.footer');
    await expect(footer).toBeVisible();
    await expect(footer.locator('.footer-name')).toContainText('UNB');
    await expect(footer.locator('.footer-tag')).toContainText('English Service');
  });

  test('Category filter switches active category in sticky nav', async ({ page }) => {
    await page.goto('/admin/service/en');
    await page.waitForLoadState('networkidle');

    const catButtons = page.locator('#wsCatNav button');
    await expect(catButtons.first()).toBeVisible({ timeout: 10000 });
    const count = await catButtons.count();

    if (count > 1) {
      const secondBtn = catButtons.nth(1);
      await secondBtn.click();
      await expect(secondBtn).toHaveClass(/active/, { timeout: 5000 });
    }
  });

  test('Rail tabs switch between Latest and Popular dispatches', async ({ page }) => {
    await page.goto('/admin/service/en');

    const popularTab = page.locator('.rail-tab', { hasText: 'Popular' });
    await expect(popularTab).toBeVisible();
    await popularTab.click();
    await expect(popularTab).toHaveClass(/active/);

    const latestTab = page.locator('.rail-tab', { hasText: 'Latest' });
    await expect(latestTab).toBeVisible();
    await latestTab.click();
    await expect(latestTab).toHaveClass(/active/);
  });

  test('Wire service settings modal opens and can be saved', async ({ page }) => {
    await page.goto('/admin/service/en');

    const settingsBtn = page.locator('button.admin-chip', { hasText: 'Settings' });
    await expect(settingsBtn).toBeVisible();
    await settingsBtn.click();

    // Modal dialog
    const modalInput = page.locator('input[wire\\:model="wireName"]');
    await expect(modalInput).toBeVisible({ timeout: 5000 });

    await modalInput.fill('UNB Premium English Wire');
    await page.click('button[type="submit"]:has-text("Save Changes")');

    // Toast feedback or modal closure
    await expect(modalInput).toBeHidden({ timeout: 5000 });
  });

  test('Bangla Wire Service frontpage renders with Bangla branding and typography', async ({ page }) => {
    await page.goto('/admin/service/bn');

    const serviceWrap = page.locator('.wire-service-wrap');
    await expect(serviceWrap).toBeVisible({ timeout: 10000 });

    await expect(page.locator('.mast-tag')).toContainText('বাংলা সার্ভিস');
    await expect(page.locator('#wsCatNav button').first()).toContainText('সকল বিভাগ');
    await expect(page.locator('.ticker-label')).toContainText('সংবাদ আপডেট');
  });
});
