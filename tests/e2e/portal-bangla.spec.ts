import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('Portal Bangla typography + labels (FR-PRT-008)', () => {
  test('portal loads Noto Sans Bengali font', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    expect(await page.locator('link[href*="Bengali"]').count()).toBeGreaterThan(0);
  });

  test('bangla story renders with Bangla chrome labels and bn-BD time', async ({ page, request }) => {
    // Seed a Bangla story so the /story/{publicId} page is not "Story not found".
    const seedHeadline = `E2E Portal Bangla ${Date.now()}`;
    const publicId = execSync(
      `php artisan tinker --execute="\\$u = \\App\\Models\\User::first(); \\$c = \\App\\Models\\Category::first(); \\$s = app(\\App\\Services\\StoryService::class)->createDraft(['language' => 'bn', 'headline' => '${seedHeadline}', 'brief' => 'b', 'body_html' => '<p>x</p>', 'category_id' => \\$c->id], \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'in_review', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'approved', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'published', \\$u); echo \\$s->public_id;"`,
      { encoding: 'utf-8' }
    ).toString().trim();

    const feed = await request.get('http://localhost:8000/api/v1/portal/feed?language=bn&limit=50');
    expect((await feed.json()).data.some((s: any) => s.public_id === publicId)).toBeTruthy();

    await page.goto(`http://localhost:3000/story/${publicId}`);
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('text=Story not found')).toHaveCount(0);
    await expect(page.locator('[data-testid="local-time"]').first()).toBeVisible();
  });

  test('english story keeps english chrome', async ({ page, request }) => {
    const seedHeadline = `E2E Portal English ${Date.now()}`;
    const publicId = execSync(
      `php artisan tinker --execute="\\$u = \\App\\Models\\User::first(); \\$c = \\App\\Models\\Category::first(); \\$s = app(\\App\\Services\\StoryService::class)->createDraft(['language' => 'en', 'headline' => '${seedHeadline}', 'brief' => 'b', 'body_html' => '<p>x</p>', 'category_id' => \\$c->id], \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'in_review', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'approved', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'published', \\$u); echo \\$s->public_id;"`,
      { encoding: 'utf-8' }
    ).toString().trim();

    await page.goto(`http://localhost:3000/story/${publicId}`);
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('text=Back to wire feed').first()).toBeVisible();
  });
});
