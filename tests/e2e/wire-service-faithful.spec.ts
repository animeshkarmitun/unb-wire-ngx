import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('Wire Service Frontpage Faithful (M8-SERV-001 / english-service.html + bn parity)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 }).catch(() => {});
  });

  test('Navigates to English Wire Service frontpage and verifies faithful prototype structure', async ({ page, request }) => {
    // Seed a published English story so the hero is guaranteed present.
    const seedHeadline = `E2E Wire Service ${Date.now()}`;
    execSync(
      `php artisan tinker --execute="\\$u = \\App\\Models\\User::first(); \\$c = \\App\\Models\\Category::first(); \\$s = app(\\App\\Services\\StoryService::class)->createDraft(['language' => 'en', 'headline' => '${seedHeadline}', 'brief' => 'b', 'body_html' => '<p>x</p>', 'category_id' => \\$c->id], \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'in_review', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'approved', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'published', \\$u);" 2>/dev/null`
    );

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

    // Hero story — guaranteed by the seeded story above.
    const heroStory = page.locator('.hero').first();
    await expect(heroStory).toBeVisible();
    await expect(heroStory.locator('.hero-img')).toBeVisible();
    await expect(heroStory.locator('.hero-overlay')).toBeVisible();
    await expect(heroStory.locator('.hero-cat')).toBeVisible();
    await expect(heroStory.locator('.hero-head')).toBeVisible();
    await expect(heroStory.locator('.hero-meta')).toBeVisible();
    await expect(heroStory).toContainText(seedHeadline);

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
    // Seeded categories guarantee at least two nav buttons.
    await expect(catButtons).toHaveCount(3, { timeout: 10000 });
    const secondBtn = catButtons.nth(1);
    await secondBtn.click();
    await expect(secondBtn).toHaveClass(/active/, { timeout: 5000 });
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

  test('Wire service settings modal opens, supports editing/toggles, cancel, and save lifecycle', async ({ page, request }) => {
    await page.goto('/admin/service/en');

    const settingsBtn = page.locator('button.admin-chip', { hasText: 'Settings' });
    await expect(settingsBtn).toBeVisible();

    // 1. Open modal and test Cancel button
    await settingsBtn.click();
    const modalInput = page.locator('input[wire\\:model="wireName"]');
    await expect(modalInput).toBeVisible({ timeout: 5000 });

    const descTextarea = page.locator('textarea[wire\\:model="description"]');
    await expect(descTextarea).toBeVisible();

    const enableCheckbox = page.locator('input[type="checkbox"][wire\\:model="enabled"]');
    await expect(enableCheckbox).toBeVisible();
    await enableCheckbox.click(); // toggle distribution

    const cancelBtn = page.locator('button.admin-chip:has-text("Cancel")');
    await cancelBtn.click();
    await expect(modalInput).toBeHidden({ timeout: 5000 });

    // 2. Re-open modal and test Save Changes
    await settingsBtn.click();
    await expect(modalInput).toBeVisible({ timeout: 5000 });
    const newName = `UNB Premium English Wire ${Date.now()}`;
    await modalInput.fill(newName);
    await descTextarea.fill('Real-time national and international dispatches.');
    await page.click('button[type="submit"]:has-text("Save Changes")');
    await expect(modalInput).toBeHidden({ timeout: 5000 });

    // Server truth: the wire_service_settings row holds the new name.
    const stored = execSync(
      `php artisan tinker --execute="echo \\DB::table('wire_service_settings')->where('language', 'en')->value('wire_name');" 2>/dev/null`,
      { encoding: 'utf8' }
    ).trim();
    expect(stored).toBe(newName);

    // 3. Re-open modal and test close '×' button
    await settingsBtn.click();
    await expect(modalInput).toBeVisible({ timeout: 5000 });
    const closeBtn = page.locator('button:has-text("×")');
    await closeBtn.click();
    await expect(modalInput).toBeHidden({ timeout: 5000 });
  });

  test('Masthead search input filters dispatches and story links navigate to reader', async ({ page }) => {
    // Seed a unique story so the search filter has a known match to assert.
    const seedHeadline = `E2E Wire Service Search ${Date.now()}`;
    execSync(
      `php artisan tinker --execute="\\$u = \\App\\Models\\User::first(); \\$c = \\App\\Models\\Category::first(); \\$s = app(\\App\\Services\\StoryService::class)->createDraft(['language' => 'en', 'headline' => '${seedHeadline}', 'brief' => 'b', 'body_html' => '<p>x</p>', 'category_id' => \\$c->id], \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'in_review', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'approved', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'published', \\$u);" 2>/dev/null`
    );

    await page.goto('/admin/service/en');
    const searchInput = page.locator('.mast-search input');
    await expect(searchInput).toBeVisible();

    await searchInput.fill(seedHeadline);
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200);
    await expect(page.locator(`.hero .hero-head:has-text("${seedHeadline}"), .rail-item:has-text("${seedHeadline}")`).first()).toBeVisible({ timeout: 10000 });

    await searchInput.fill('');
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200);

    // The hero or a rail item now points to a real story reader.
    const storyLink = page.locator('a.story-card, a.hero, a.rail-item').first();
    await expect(storyLink).toBeVisible();
    await expect(storyLink).toHaveAttribute('href', /.*admin\/story\/.*/);
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
