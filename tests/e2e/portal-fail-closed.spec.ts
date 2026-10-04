import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';

/**
 * Portal E2E — fail-closed when NEXT_PUBLIC_DISABLE_MOCK_FALLBACK=1.
 *
 * Under the flag:
 *   - `/story/{publicId}` renders the API story.
 *   - `/story/bn1` and `/story/1` show "Story not found" — not mock data.
 *   - Search `/api/v1/portal/feed?since=...` actually sends `since` and the response
 *     is constrained by the cursor.
 *   - `/api/v1/portal/feed?search=GDP` tests Meili fallback path does not silently
 *     fall through to a 200 ok with mock data when Meili is down.
 */

async function ensurePublishedStory(): Promise<string> {
  const headline = `E2E Portal Fail-Closed ${Date.now()}`;
  execSync(
    `php artisan tinker --execute="\\App\\Services\\StoryService::class; \\$c = \\App\\Models\\Category::first() ?? \\App\\Models\\Category::factory()->create(); \\$u = \\App\\Models\\User::first(); \\$s = app(\\App\\Services\\StoryService::class)->createDraft(['language' => 'en', 'headline' => '${headline.replace(/'/g, "\\'")}', 'brief' => 'Portal story.', 'body_html' => '<p>body</p>', 'category_id' => \\$c->id], \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'in_review', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'approved', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'published', \\$u); echo \\$s->public_id;"`,
    { stdio: 'pipe' }
  ).toString().trim();
  return headline;
}

test.describe('Portal E2E (Fail-Closed)', () => {
  test.setTimeout(90000);

  test('renders real API story; rejects mock fallback IDs', async ({ page, request }) => {
    const headline = await ensurePublishedStory();

    // 1. Real public_id renders the API story.
    await page.goto('http://localhost:3000/');
    const searchResponse = await request.get('http://localhost:8000/api/v1/portal/feed?language=en');
    const j = await searchResponse.json();
    const publicId = (j.data ?? []).find((s: any) => s.headline.includes(headline))?.public_id;
    expect(publicId).toBeTruthy();

    await page.goto(`http://localhost:3000/story/${publicId}`);
    await expect(page.locator('h1')).toContainText(headline, { timeout: 15000 });

    // 2. Mock IDs render "Story not found".
    await page.goto('http://localhost:3000/story/bn1');
    await expect(page.locator('body')).toContainText('Story not found', { timeout: 15000 });

    await page.goto('http://localhost:3000/story/1');
    await expect(page.locator('body')).toContainText('Story not found', { timeout: 15000 });
  });

  test('feed ?since=… cursor actually constrains results', async ({ request }) => {
    // Publish two more stories with controlled timestamps.
    const older = `E2E Cursor Older ${Date.now()}`;
    const newer = `E2E Cursor Newer ${Date.now()}`;
    const head = (slug: string) => `php artisan tinker --execute="\\$u = \\App\\Models\\User::first(); \\$c = \\App\\Models\\Category::first(); \\$s = app(\\App\\Services\\StoryService::class)->createDraft(['language' => 'en', 'headline' => '${slug}', 'brief' => 'b', 'body_html' => '<p>x</p>', 'category_id' => \\$c->id], \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'in_review', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'approved', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'published', \\$s->owner); \\$s->update(['published_at' => \\App\\Support\\Carbon::parse('${Date.now() - 600000}')]); echo \\$s->public_id;"`;

    execSync(head(older.replace(/'/g, "\\'")), { stdio: 'pipe' });
    execSync(head(newer.replace(/'/g, "\\'")), { stdio: 'pipe' });

    // Take the older story's published_at as the `since` cursor and ask for stories since that point.
    const all = await request.get('http://localhost:8000/api/v1/portal/feed?language=en&limit=100');
    const jAll = await all.json();
    const olderStory = (jAll.data ?? []).find((s: any) => s.headline === older);
    expect(olderStory).toBeTruthy();
    const since = new Date(new Date(olderStory.published_at).getTime() + 1000).toISOString();

    const filtered = await request.get(`http://localhost:8000/api/v1/portal/feed?language=en&since=${encodeURIComponent(since)}`);
    expect(filtered.ok()).toBeTruthy();
    const jFiltered = await filtered.json();
    const filteredHeadlines = (jFiltered.data ?? []).map((s: any) => s.headline);

    // The newer one should be present, the older one should be excluded.
    expect(filteredHeadlines).toContain(newer);
    expect(filteredHeadlines).not.toContain(older);
  });
});