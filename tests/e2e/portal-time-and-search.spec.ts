import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('Portal local-time + Meilisearch wiring (FR-PRT-003/007)', () => {
  test('story reader shows consumer time with Dhaka secondary label', async ({ page }) => {
    const seedHeadline = `E2E Portal Time ${Date.now()}`;
    const publicId = execSync(
      `php artisan tinker --execute="\\$u = \\App\\Models\\User::first(); \\$c = \\App\\Models\\Category::first(); \\$s = app(\\App\\Services\\StoryService::class)->createDraft(['language' => 'en', 'headline' => '${seedHeadline}', 'brief' => 'b', 'body_html' => '<p>x</p>', 'category_id' => \\$c->id], \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'in_review', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'approved', \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'published', \\$u); echo \\$s->public_id;"`,
      { encoding: 'utf-8' }
    ).toString().trim();

    await page.goto(`http://localhost:3000/story/${publicId}`);
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('text=Story not found')).toHaveCount(0);
    await expect(page.locator('[data-testid="local-time"]').first()).toBeVisible({ timeout: 15000 });
    await expect(page.locator('[data-testid="local-time"]').first()).toContainText(/Dhaka/i);
  });

  test('feed clock shows Dhaka as secondary label', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('text=Dhaka').first()).toBeVisible();
  });

  test('search shows match count and survives zero-result query', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    const input = page.locator('#omniInput, #portalSearch').first();
    await input.fill('Rizvi');
    await expect(page.locator('body')).toContainText(/Rizvi/i, { timeout: 10000 });
    await input.fill('zzzzunlikelyzzzz');
    await expect(page.locator('body')).toContainText(/0 stor|no stor|No stories/i, { timeout: 10000 });
  });
});
