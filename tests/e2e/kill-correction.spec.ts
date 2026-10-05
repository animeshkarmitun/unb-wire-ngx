import { test, expect, Page, APIRequestContext } from '@playwright/test';
import { execSync, spawnSync } from 'child_process';

/**
 * M14-COV-019 — real kill / correction / unpublish journey.
 * - Admin publishes an en politics story via the wizard.
 * - The entitled client key (Daily Star: en + National Politics) sees it live.
 * - The non-recipient key (Prothom Alo: bn-only) never sees it — before or after the kill.
 * - Admin kills via the story-reader "Kill / Unpublish Story" button (wire:confirm accepted).
 * - Prior recipient's feed shows the tombstone (status=killed, is_killed, STORY KILLED brief).
 * - index_outbox carries the synchronous op=delete row for the killed public_id.
 * - A second live story is corrected through StoryService::updateDraft; the feed shows
 *   the revised headline and an op=upsert outbox row.
 * - The news-list live toggle (published -> archived) drops the headline from /api/v1/portal/feed.
 *
 * Feed reads carry a unique `cb` param: both feed endpoints cache per full URL (60s TTL)
 * and parameterized URLs are not covered by the transition-time invalidation sweep, so
 * each read must observe server truth instead of a stale cache window.
 */

const ADMIN = { email: 'test@example.com', password: 'password' };
const DS_KEY = 'unb_live_testkey_dailystar_001';
const PA_KEY = 'unb_live_testkey_prothomalo_005';
const API_FEED = 'http://localhost:8000/api/v1/feed';
const PORTAL_FEED = 'http://localhost:8000/api/v1/portal/feed';

function tinker(code: string): string {
  // spawnSync without a shell: identical argv passing on Windows (cmd) and CI (bash).
  // PHP snippets must therefore avoid double quotes (single quotes only).
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

test.describe('Kill / Correction / Unpublish E2E', () => {
  test.beforeAll(() => {
    execSync('php tests/e2e/helpers/seed-data.php wire_api', { stdio: 'pipe' });
  });

  test.setTimeout(180000);

  test('kill tombstones the entitled key only, correction re-flows the feed, live toggle drops it', async ({ page, request }) => {
    page.on('dialog', (d) => d.accept());
    await login(page);

    const politicsId = tinker(`echo \\App\\Models\\Category::where('slug','national-politics')->value('id');`);
    expect(politicsId).toMatch(/^\d+$/);

    // 1. Publish an en/politics story through the wizard.
    const headline = `E2E Kill Tombstone ${Date.now()}`;
    await page.goto('/admin/add-news');
    // Fill only after Livewire hydration, otherwise the initial morph resets the
    // local input values and step validation sees an empty headline.
    await page.waitForFunction(() => !!(window as any).Livewire);
    await page.waitForLoadState('networkidle');
    await page.fill('#headlineInput', headline);
    await page.fill('#briefInput', 'Initial brief for the kill journey.');
    await page.waitForLoadState('networkidle');
    await page.click('.stp[data-go="3"]');
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200, { timeout: 15000 });
    await page.locator('#catSelect').selectOption({ value: politicsId });
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200, { timeout: 15000 });
    await page.click('#nextBtn');
    await expect(page.locator('#publishBtn')).toBeVisible({ timeout: 10000 });
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200, { timeout: 30000 }),
      page.click('#publishBtn'),
    ]);
    await expect(page.locator('#successCard')).toHaveClass(/show/, { timeout: 15000 });

    const publicId = tinker(`echo \\App\\Models\\Story::where('headline','${headline}')->value('public_id');`);
    expect(publicId).toMatch(/^01[A-Z0-9]+$/i);

    // 2. Daily Star (entitled) sees it live on the client API feed.
    await expect.poll(async () => {
      const j = await feedJson(request, `${API_FEED}?language=en`, DS_KEY);
      return (j.data ?? []).some((s: any) => s.headline === headline && s.status === 'published');
    }, { timeout: 20000, message: 'story never appeared on the DS feed' }).toBe(true);

    // 3. Prothom Alo (bn-only package) never received it — baseline.
    expect(JSON.stringify(await feedJson(request, API_FEED, PA_KEY))).not.toContain(headline);

    // 4. Kill via the story-reader control (real UI, no tinker fallback).
    await page.goto(`/admin/story/${publicId}`);
    const killBtn = page.locator('button:has-text("Kill / Unpublish Story")');
    await expect(killBtn).toBeVisible({ timeout: 10000 });
    await killBtn.evaluate((el) => el.scrollIntoView({ block: 'center' }));
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200, { timeout: 30000 }),
      killBtn.click(),
    ]);

    // 5. Prior-entitled feed now shows the tombstone.
    await expect.poll(async () => {
      const j = await feedJson(request, `${API_FEED}?language=en`, DS_KEY);
      const item = (j.data ?? []).find((s: any) => s.headline === headline);
      return !!item && item.status === 'killed' && item.is_killed === true && /STORY KILLED/i.test(item.brief);
    }, { timeout: 20000, message: 'DS feed did not tombstone the killed story' }).toBe(true);

    // 6. Prothom Alo's feed does not gain the kill item.
    expect(JSON.stringify(await feedJson(request, API_FEED, PA_KEY))).not.toContain(headline);

    // 7. index_outbox carries the main-delete row written by the kill transition.
    const hasDelete = tinker(
      `var_export(\\Illuminate\\Support\\Facades\\DB::table('index_outbox')->where('document_id','${publicId}')->where('op','delete')->exists(), true);`
    );
    expect(hasDelete).toContain('true');

    // 8. Correction journey on a separate live story.
    const corrBase = `E2E Correction Base ${Date.now()}`;
    tinker(
      `$u=\\App\\Models\\User::first(); $svc=app(\\App\\Services\\StoryService::class); $s=$svc->createDraft(['language'=>'en','headline'=>'${corrBase}','brief'=>'pre-correction brief','body_html'=>'<p>body</p>','category_id'=>${politicsId}], $u); $svc->transition($s,'in_review',$u); $s=$s->fresh(); $svc->transition($s,'approved',$u); $s=$s->fresh(); $svc->transition($s,'published',$u); echo $s->public_id;`
    );
    await expect.poll(async () => {
      const j = await feedJson(request, `${API_FEED}?language=en`, DS_KEY);
      return (j.data ?? []).some((s: any) => s.headline === corrBase);
    }, { timeout: 20000, message: 'correction story never appeared on the DS feed' }).toBe(true);

    const corrected = `${corrBase} (CORRECTED)`;
    tinker(
      `$s=\\App\\Models\\Story::where('headline','${corrBase}')->firstOrFail(); app(\\App\\Services\\StoryService::class)->updateDraft($s, ['headline'=>'${corrected}','brief'=>'corrected brief'], $s->version, $s->owner);`
    );

    // 9. The prior recipient sees the corrected headline.
    await expect.poll(async () => {
      const j = await feedJson(request, `${API_FEED}?language=en`, DS_KEY);
      return (j.data ?? []).some((s: any) => s.headline === corrected && s.status === 'published');
    }, { timeout: 20000, message: 'corrected headline did not reach the DS feed' }).toBe(true);

    // 10. The correction wrote an outbox upsert for the same document.
    const corrId = tinker(`echo \\App\\Models\\Story::where('headline','${corrected}')->value('public_id');`);
    const hasUpsert = tinker(
      `var_export(\\Illuminate\\Support\\Facades\\DB::table('index_outbox')->where('document_id','${corrId}')->where('op','upsert')->exists(), true);`
    );
    expect(hasUpsert).toContain('true');

    // 11. Unpublish via the news-list live switch: headline leaves the portal feed.
    await page.goto('/admin/news/en');
    const row = page.locator(`.news-table tbody tr:has-text("${corrected}")`).first();
    await expect(row).toBeVisible({ timeout: 10000 });

    const portalBefore = await feedJson(request, `${PORTAL_FEED}?language=en`);
    expect(JSON.stringify(portalBefore)).toContain(corrected);

    const sw = row.locator('label.switch');
    await sw.evaluate((el) => el.scrollIntoView({ block: 'center' }));
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200, { timeout: 30000 }),
      sw.click(),
    ]);

    await expect.poll(async () => {
      const j = await feedJson(request, `${PORTAL_FEED}?language=en`);
      return JSON.stringify(j).includes(corrected);
    }, { timeout: 20000, message: 'live toggle did not remove the story from the portal feed' }).toBe(false);
  });
});
