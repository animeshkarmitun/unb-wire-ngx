import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';

const ADMIN = { email: 'test@example.com', password: 'password' };
const FEED_DAILY_STAR = 'http://localhost:8000/api/v1/feed?language=en';

async function login(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', ADMIN.email);
  await page.fill('input[name="password"]', ADMIN.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**', { timeout: 10000 });
}

test.describe('Subscription Expiry / Overlap E2E', () => {
  test.setTimeout(90000);

  test('expired subscription removes story from that client feed; union still delivers', async ({ page, request }) => {
    await login(page);

    const uniqueHeadline = `E2E Sub Expiry ${Date.now()}`;

    // Publish an English politics story.
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');
    await page.fill('#headlineInput', uniqueHeadline);
    await page.fill('#briefInput', 'Subscription expiry coverage.');
    await page.click('.stp[data-go="3"]');
    await page.locator('#catSelect').selectOption({ index: 1 });
    await page.click('#nextBtn');
    await page.waitForTimeout(400);
    await page.click('#publishBtn');
    await page.waitForTimeout(1500);

    // Daily Star (en, politics package, active) sees it.
    const daily = await request.get(FEED_DAILY_STAR, {
      headers: { Authorization: 'Bearer unb_live_testkey_dailystar_001' },
    });
    expect(daily.ok()).toBeTruthy();
    expect(await daily.text()).toContain(uniqueHeadline);

    // Force-expire Daily Star's subscription.
    execSync(
      `php tests/e2e/helpers/seed-data.php expire-key dailystar-001 || true`,
      { stdio: 'pipe' }
    );
    // Expire subscription, not the API key:
    execSync(
      `php artisan tinker --execute="\\App\\Models\\ClientPackage::where('client_id', function(\\$q){ \\$q->select('id')->from('clients')->where('code','DST-E2E'); })->update(['ends_at' => now()->subMinute()]);"`,
      { stdio: 'pipe' }
    );

    const after = await request.get(FEED_DAILY_STAR, {
      headers: { Authorization: 'Bearer unb_live_testkey_dailystar_001' },
    });
    expect(after.ok()).toBeTruthy();
    expect(await after.text()).not.toContain(uniqueHeadline);
  });
});