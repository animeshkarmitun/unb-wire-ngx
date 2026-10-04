import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

const LARAVEL = process.env.LARAVEL_URL ?? 'http://localhost:8000';

test.describe('Portal story detail + pagination + Meili keydown', () => {
  test('GET /api/v1/portal/story/{publicId} 200 with headline', async ({ request }) => {
    const seedHeadline = `E2E Story Detail ${Date.now()}`;
    const publicId = execSync(
      `php artisan tinker --execute="\\$u = \\App\\Models\\User::first(); \\$c = \\App\\Models\\Category::first(); \\$s = app(\\App\\Services\\StoryService::class)->createDraft(['language' => 'en', 'headline' => '${seedHeadline}', 'brief' => 'b', 'body_html' => '<p>x</p>', 'category_id' => \\$c->id], \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'in_review', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'approved', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'published', \\$u); echo \\$s->public_id;"`,
      { encoding: 'utf-8' }
    ).toString().trim();

    const r = await request.get(`${LARAVEL}/api/v1/portal/story/${publicId}`);
    expect(r.ok()).toBeTruthy();
    const j = await r.json();
    expect(j.data.headline).toBe(seedHeadline);
    expect(j.data.published_at).toContain('T');
  });

  test('GET /api/v1/portal/feed cursor pagination ?since', async ({ request }) => {
    // Seed two stories with controlled timestamps.
    const older = `E2E Cursor Older ${Date.now()}`;
    const newer = `E2E Cursor Newer ${Date.now()}`;
    const publish = (slug: string, msAgo: number) => `php artisan tinker --execute="\\$u = \\App\\Models\\User::first(); \\$c = \\App\\Models\\Category::first(); \\$s = app(\\App\\Services\\StoryService::class)->createDraft(['language' => 'en', 'headline' => '${slug}', 'brief' => 'b', 'body_html' => '<p>x</p>', 'category_id' => \\$c->id], \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'in_review', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'approved', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'published', \\$u); \\$s->update(['published_at' => now()->subMs(${msAgo})]); echo \\$s->public_id;"`;
    execSync(publish(older, 600_000), { stdio: 'pipe' });
    execSync(publish(newer, 60_000), { stdio: 'pipe' });

    const all = await request.get(`${LARAVEL}/api/v1/portal/feed?language=en&limit=100`);
    const jAll = await all.json();
    const olderStory = (jAll.data ?? []).find((s: any) => s.headline === older);
    expect(olderStory).toBeTruthy();
    const since = new Date(new Date(olderStory.published_at).getTime() + 1000).toISOString();

    const filtered = await request.get(`${LARAVEL}/api/v1/portal/feed?language=en&since=${encodeURIComponent(since)}`);
    expect(filtered.ok()).toBeTruthy();
    const jFiltered = await filtered.json();
    const filteredHeadlines = (jFiltered.data ?? []).map((s: any) => s.headline);
    expect(filteredHeadlines).toContain(newer);
    expect(filteredHeadlines).not.toContain(older);
  });

  test('Portal Meili search keydown does not crash (Enter)', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    const input = page.locator('#omniInput, #portalSearch').first();
    await expect(input).toBeVisible({ timeout: 10000 });
    await input.fill('Bangladesh');
    await input.press('Enter');
    await page.waitForTimeout(500);
    await expect(page.locator('body')).toContainText(/Bangladesh|News wire|Wire/i);
    expect(page.url()).toContain('localhost:3000');
  });

  test('Portal story page /story/{publicId} renders the API story', async ({ page, request }) => {
    const seedHeadline = `E2E Story Detail Render ${Date.now()}`;
    const publicId = execSync(
      `php artisan tinker --execute="\\$u = \\App\\Models\\User::first(); \\$c = \\App\\Models\\Category::first(); \\$s = app(\\App\\Services\\StoryService::class)->createDraft(['language' => 'en', 'headline' => '${seedHeadline}', 'brief' => 'b', 'body_html' => '<p>x</p>', 'category_id' => \\$c->id], \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'in_review', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'approved', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'published', \\$u); echo \\$s->public_id;"`,
      { encoding: 'utf-8' }
    ).toString().trim();

    const api = await request.get(`${LARAVEL}/api/v1/portal/story/${publicId}`);
    expect(api.ok()).toBeTruthy();

    await page.goto(`http://localhost:3000/story/${publicId}`);
    await expect(page.locator('body')).toContainText(seedHeadline, { timeout: 15000 });
  });
});
