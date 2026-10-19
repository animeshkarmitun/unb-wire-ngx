import { test, expect, Page, APIRequestContext } from '@playwright/test';
import { execSync, spawnSync } from 'child_process';

/**
 * Golden Path E2E — Publish to Client Journey
 *
 * Proves the full happy-path from a single test run:
 *   1. Admin publishes a story through the wizard (en / National Politics)
 *   2. Story appears on the portal feed  (/api/v1/portal/feed)
 *   3. Story detail is accessible        (/api/v1/portal/story/{id})
 *   4. Search token is issued with valid filter  (/api/v1/portal/search-token)
 *   5. Story appears on the wire API feed (/api/v1/feed) for the entitled client
 *   6. Non-entitled client does NOT see the story
 *
 * Requires: `php tests/e2e/helpers/seed-data.php wire_api` (run in beforeAll).
 * Seeded keys: Daily Star (en+politics) = unb_live_testkey_dailystar_001
 *              Prothom Alo (bn-only)    = unb_live_testkey_prothomalo_005
 */

const ADMIN = { email: 'test@example.com', password: 'password' };
const DS_KEY = 'unb_live_testkey_dailystar_001';
const PA_KEY = 'unb_live_testkey_prothomalo_005';
const API_FEED = 'http://localhost:8000/api/v1/feed';
const PORTAL_FEED = 'http://localhost:8000/api/v1/portal/feed';
const PORTAL_STORY = 'http://localhost:8000/api/v1/portal/story';
const SEARCH_TOKEN = 'http://localhost:8000/api/v1/portal/search-token';

function tinker(code: string): string {
  const r = spawnSync('php', ['artisan', 'tinker', '--execute', code], { encoding: 'utf-8' });
  if (r.status !== 0) throw new Error(`tinker failed: ${r.stderr}`);
  return (r.stdout ?? '').trim();
}

async function feedJson(request: APIRequestContext, url: string, key?: string): Promise<any> {
  const sep = url.includes('?') ? '&' : '?';
  const res = await request.get(`${url}${sep}limit=50&cb=${Date.now()}_${Math.random()}`, {
    headers: key ? { Authorization: `Bearer ${key}` } : {},
  });
  expect(res.ok(), `feed request failed: ${res.status()}`).toBeTruthy();
  return res.json();
}

async function login(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', ADMIN.email);
  await page.fill('input[name="password"]', ADMIN.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**', { timeout: 10000 });
}

test.describe('Publish to Client Journey (Golden Path)', () => {
  test.beforeAll(() => {
    execSync('php tests/e2e/helpers/seed-data.php wire_api', { stdio: 'pipe' });
  });

  test.setTimeout(120000);

  test('Admin publishes story → subscriber sees it on portal feed, search, and wire API', async ({ page, request }) => {
    page.on('dialog', (d) => d.accept());
    await login(page);

    const politicsId = tinker(`echo \\App\\Models\\Category::where('slug','national-politics')->value('id');`);
    expect(politicsId).toMatch(/^\d+$/);

    // ── 1. Publish an en/politics story through the wizard ──────────────
    const headline = `E2E Golden Path ${Date.now()}`;
    const brief = 'Automated golden path E2E test brief for the publish-to-client journey.';

    await page.goto('/admin/add-news');
    await page.waitForFunction(() => !!(window as any).Livewire);
    await page.waitForLoadState('networkidle');
    await page.fill('#headlineInput', headline);
    await page.fill('#briefInput', brief);
    await page.waitForLoadState('networkidle');

    // Skip media (step 2) → Organize & access (step 3)
    await page.click('.stp[data-go="3"]');
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200, { timeout: 15000 });

    // Select National Politics category
    await page.locator('#catSelect').selectOption({ value: politicsId });
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200, { timeout: 15000 });

    // Review & publish (step 4)
    await page.click('#nextBtn');
    await expect(page.locator('#publishBtn')).toBeVisible({ timeout: 10000 });

    // Publish
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200, { timeout: 30000 }),
      page.click('#publishBtn'),
    ]);
    await expect(page.locator('#successCard')).toHaveClass(/show/, { timeout: 15000 });

    // Get the public_id from the database
    const publicId = tinker(`echo \\App\\Models\\Story::where('headline','${headline}')->value('public_id');`);
    expect(publicId).toMatch(/^01[A-Z0-9]+$/i);

    // ── 2. Portal Feed: story appears ───────────────────────────────────
    await expect.poll(async () => {
      const j = await feedJson(request, `${PORTAL_FEED}?language=en`);
      return (j.data ?? []).some((s: any) => s.headline === headline);
    }, { timeout: 20000, message: 'story never appeared on the portal feed' }).toBe(true);

    // ── 3. Portal Story Detail: accessible by public_id ─────────────────
    const storyRes = await request.get(`${PORTAL_STORY}/${publicId}?cb=${Date.now()}`);
    expect(storyRes.ok()).toBeTruthy();
    const storyJson = await storyRes.json();
    expect(storyJson.data.headline).toBe(headline);

    // ── 4. Search Token: valid JWT with language filter ──────────────────
    const tokenRes = await request.post(SEARCH_TOKEN);
    expect(tokenRes.ok()).toBeTruthy();
    const tokenJson = await tokenRes.json();
    expect(tokenJson.token.split('.').length).toBe(3);
    expect(tokenJson.filter).toContain('language IN');
    expect(tokenJson.host).toBeTruthy();

    // ── 5. Wire API: entitled client (Daily Star) sees the story ────────
    await expect.poll(async () => {
      const j = await feedJson(request, `${API_FEED}?language=en`, DS_KEY);
      return (j.data ?? []).some((s: any) => s.headline === headline && s.status === 'published');
    }, { timeout: 20000, message: 'story never appeared on the Daily Star wire API feed' }).toBe(true);

    // ── 6. Wire API: non-entitled client (Prothom Alo, bn-only) does NOT see it
    const paFeed = await feedJson(request, API_FEED, PA_KEY);
    expect(JSON.stringify(paFeed)).not.toContain(headline);

    // ── 7. Server truth: story_events confirms publish ──────────────────
    const events = tinker(
      `echo \\App\\Models\\Story::where('headline','${headline}')->first()?->events()->pluck('action')->implode(',');`
    );
    expect(events).toContain('published');
  });
});
