import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';

const ADMIN = { email: 'test@example.com', password: 'password' };
const FEED = 'http://localhost:8000/api/v1/portal/feed?language=en';
const LARAVEL = 'http://localhost:8000';

async function login(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', ADMIN.email);
  await page.fill('input[name="password"]', ADMIN.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**', { timeout: 10000 });
}

test.describe('Embargo Then Lift E2E', () => {
  test.setTimeout(90000);

  test('publish under future embargo is absent from feed until lift runs', async ({ page, request }) => {
    await login(page);

    // 1. Admin creates a story with a future embargo (2 minutes from now in Asia/Dhaka).
    const uniqueHeadline = `Embargo Lift E2E ${Date.now()}`;
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');
    await page.fill('#headlineInput', uniqueHeadline);
    await page.fill('#briefInput', 'Embargoed body content.');

    await page.click('.stp[data-go="3"]');
    await page.locator('#catSelect').selectOption({ index: 1 });

    // Embargo field — wait for it to render.
    const embargoInput = page.locator('#embargoInput, input[name="embargo_until"], input[name*="embargo"]').first();
    await expect(embargoInput).toBeVisible({ timeout: 5000 });

    // Pick "2 minutes from now" via JS on the input.
    const futureIso = new Date(Date.now() + 2 * 60_000).toISOString();
    await embargoInput.fill(futureIso.slice(0, 16));
    await page.waitForTimeout(200);

    await page.click('#nextBtn');
    await page.waitForTimeout(400);

    // 2. Submit (this routes through the service which respects embargo).
    await page.click('#publishBtn');
    await page.waitForTimeout(1500);

    // 3. Feed must NOT contain the headline yet (embargo in the future).
    const beforeFeed = await request.get(FEED);
    expect(beforeFeed.ok()).toBeTruthy();
    const beforeText = await beforeFeed.text();
    expect(beforeText).not.toContain(uniqueHeadline);

    // 4. Force past the embargo and run the real lift invokable — not a raw UPDATE.
    execSync(
      `php artisan tinker --execute="\\App\\Models\\Story::where('headline','${uniqueHeadline.replace(/'/g, "\\'")}')->update(['embargo_until' => now()->subMinute()]);"`,
      { stdio: 'pipe' }
    );
    execSync('php artisan tinker --execute="app(\\App\\Console\\Scheduling\\LiftEmbargoedStories::class)->handle();"', { stdio: 'pipe' });

    // 5. The story is now published; the feed must contain it.
    const afterFeed = await request.get(FEED);
    expect(afterFeed.ok()).toBeTruthy();
    const afterText = await afterFeed.text();
    expect(afterText).toContain(uniqueHeadline);

    // 6. A second story with a future embargo stays absent when the lift runs.
    const secondHeadline = `Embargo Lift E2E Future ${Date.now()}`;
    await page.goto('/admin/add-news');
    await page.fill('#headlineInput', secondHeadline);
    await page.fill('#briefInput', 'Still in the future.');
    await page.click('.stp[data-go="3"]');
    await page.locator('#catSelect').selectOption({ index: 1 });
    await embargoInput.fill(new Date(Date.now() + 60 * 60_000).toISOString().slice(0, 16));
    await page.waitForTimeout(200);
    await page.click('#nextBtn');
    await page.waitForTimeout(400);
    await page.click('#publishBtn');
    await page.waitForTimeout(1500);

    execSync('php artisan tinker --execute="app(\\App\\Console\\Scheduling\\LiftEmbargoedStories::class)->handle();"', { stdio: 'pipe' });

    const finalFeed = await request.get(FEED);
    const finalText = await finalFeed.text();
    expect(finalText).not.toContain(secondHeadline);
  });
});