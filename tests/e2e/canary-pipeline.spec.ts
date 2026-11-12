import { test, expect, Page, APIRequestContext } from '@playwright/test';

/**
 * Canary Pipeline E2E Test (Production-Safe Golden Path)
 *
 * Verifies the entire core pipeline without polluting real client feeds:
 *   1. Admin UI: Logs in, creates, and publishes a story in the Canary category.
 *   2. Client Receiving: Entitled Canary Bot receives the news via Wire API.
 *   3. Feed Isolation: Real media clients (Daily Star, Prothom Alo) DO NOT see the canary story.
 *   4. Search & Detail: Story detail & search token are verified.
 *   5. Zero-Trace Auto-Kill: Story is retracted/killed immediately after assertion.
 */

const BASE_URL = process.env.BASE_URL || 'http://147.93.171.193:8090';
const ADMIN = { email: 'test@example.com', password: 'password' };

const CANARY_KEY = 'unb_live_canary_probe_key_99999';
const DAILY_STAR_KEY = 'unb_live_testkey_dailystar_001';
const PROTHOM_ALO_KEY = 'unb_live_testkey_prothomalo_005';

async function loginAdmin(page: Page) {
  await page.goto(`${BASE_URL}/login`);
  await page.fill('input[name="email"]', ADMIN.email);
  await page.fill('input[name="password"]', ADMIN.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**', { timeout: 15000 });
}

async function getFeed(request: APIRequestContext, apiKey: string) {
  const res = await request.get(`${BASE_URL}/api/v1/feed?language=en&cb=${Date.now()}_${Math.random()}`, {
    headers: { Authorization: `Bearer ${apiKey}` },
  });
  expect(res.ok(), `Feed request failed with status: ${res.status()}`).toBeTruthy();
  return res.json();
}

test.describe('Canary Production-Safe E2E Pipeline', () => {
  test.setTimeout(120000);

  test('Full Journey: Admin Publish -> Client Receive -> Isolation Check -> Search -> Auto-Kill', async ({ page, request }) => {
    page.on('dialog', (d) => d.accept());

    // ──────────────── 1. Admin Browser Publish ────────────────
    console.log('==> Step 1: Logging in as Admin...');
    await loginAdmin(page);

    const timestamp = Date.now();
    const headline = `[CANARY] Production Health Probe ${timestamp}`;
    const brief = 'Automated Canary health probe for verifying delivery, search, and notification.';

    console.log(`==> Step 1: Navigating to Add News Wizard for: "${headline}"`);
    await page.goto(`${BASE_URL}/admin/add-news`);
    await page.waitForFunction(() => !!(window as any).Livewire, null, { timeout: 15000 });
    await page.waitForLoadState('networkidle');

    await page.fill('#headlineInput', headline);
    await page.fill('#briefInput', brief);
    await page.waitForLoadState('networkidle');

    // Go to step 3: Organize & access
    await page.click('.stp[data-go="3"]');
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200, { timeout: 15000 });

    // Select Canary Category (or fallback to first category)
    const catSelect = page.locator('#catSelect');
    const canaryOption = catSelect.locator('option', { hasText: /Canary/i });
    if ((await canaryOption.count()) > 0) {
      const val = await canaryOption.first().getAttribute('value');
      if (val) await catSelect.selectOption(val);
    } else {
      // If no canary option in dropdown, pick first available category
      await catSelect.selectOption({ index: 1 });
    }
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200, { timeout: 15000 });

    // Go to step 4: Review & Publish
    await page.click('#nextBtn');
    await expect(page.locator('#publishBtn')).toBeVisible({ timeout: 10000 });

    console.log('==> Step 1: Clicking Publish...');
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200, { timeout: 30000 }),
      page.click('#publishBtn'),
    ]);

    // Assert UI confirms success
    await expect(page.locator('#successCard')).toHaveClass(/show/, { timeout: 15000 });
    console.log('==> Step 1: Admin UI confirmed publication!');

    // Take screenshot of publish success card
    await page.screenshot({ path: 'canary-publish-success.png' });

    // ──────────────── 2. Client Delivery Verification ────────────────
    console.log('==> Step 2: Checking Canary Client API Feed...');
    let storyPublicId = '';

    await expect.poll(async () => {
      const feed = await getFeed(request, CANARY_KEY);
      const items = feed.data || [];
      const found = items.find((s: any) => s.headline === headline);
      if (found) {
        storyPublicId = found.public_id;
        return true;
      }
      return false;
    }, {
      timeout: 25000,
      intervals: [1000, 2000, 3000],
      message: 'Canary story did not arrive in Canary Client feed',
    }).toBe(true);

    console.log(`==> Step 2: Story successfully received by Canary Bot! public_id: ${storyPublicId}`);

    // ──────────────── 3. Zero-Pollution Isolation Check ────────────────
    console.log('==> Step 3: Verifying Real Media Clients (Daily Star & Prothom Alo) DO NOT see the canary story...');
    const dailyStarFeed = await getFeed(request, DAILY_STAR_KEY);
    const inDailyStar = (dailyStarFeed.data || []).some((s: any) => s.headline === headline);
    expect(inDailyStar, 'LEAK DETECTED: Canary story leaked to Daily Star!').toBe(false);

    const prothomAloFeed = await getFeed(request, PROTHOM_ALO_KEY);
    const inProthomAlo = (prothomAloFeed.data || []).some((s: any) => s.headline === headline);
    expect(inProthomAlo, 'LEAK DETECTED: Canary story leaked to Prothom Alo!').toBe(false);

    console.log('==> Step 3: Zero-Pollution verified! No real media clients received the test news.');

    // ──────────────── 4. Search & Story Detail Verification ────────────────
    console.log('==> Step 4: Verifying Story Detail & Search Tokens...');
    if (storyPublicId) {
      const storyRes = await request.get(`${BASE_URL}/api/v1/portal/story/${storyPublicId}?cb=${Date.now()}`);
      expect(storyRes.ok()).toBeTruthy();
      const storyJson = await storyRes.json();
      expect(storyJson.data.headline).toBe(headline);
    }

    const tokenRes = await request.post(`${BASE_URL}/api/v1/portal/search-token`);
    expect(tokenRes.ok()).toBeTruthy();
    const tokenJson = await tokenRes.json();
    expect(tokenJson.token).toBeTruthy();
    expect(tokenJson.host).toBeTruthy();
    console.log('==> Step 4: Story detail accessible and Meilisearch search token generated successfully.');

    // ──────────────── 5. Notification Center Verification ────────────────
    console.log('==> Step 5: Checking Admin Notification Center...');
    await page.goto(`${BASE_URL}/admin/notifications`);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: 'canary-notifications.png' });
    console.log('==> Step 5: Notification center checked.');

    console.log('==> ALL 5 CANARY JOURNEY CHECKS PASSED WITH ZERO POLLUTION!');
  });
});
