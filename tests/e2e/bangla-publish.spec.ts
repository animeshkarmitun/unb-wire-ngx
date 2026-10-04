import { test, expect, Page, request as apiRequest } from '@playwright/test';
import { execSync } from 'child_process';

/**
 * E2E: Admin publishes a Bangla story via the wizard (language=bn).
 * - /admin/news/bn shows the headline.
 * - GET /api/v1/portal/feed?language=bn contains the headline.
 * - GET /api/v1/portal/story/{publicId} and /api/v1/feed responses DO NOT
 *   contain the internal-note string (NEWSROOM-NOTE-UNIQUE).
 *
 * Uses the seeded DST-E2E client (en) and a Prothom-Alo-equivalent seeded client (bn).
 */

const ADMIN = { email: 'test@example.com', password: 'password' };
const NEWSROOM_NOTE = `NEWSROOM-NOTE-UNIQUE-${Date.now()}`;

async function login(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', ADMIN.email);
  await page.fill('input[name="password"]', ADMIN.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**', { timeout: 10000 });
}

test.describe('Bangla Publish + Notes Isolation E2E', () => {
  test.setTimeout(90000);

  test('publish Bangla story and verify on bn list, bn feed, and no internal note in client payload', async ({ page, request }) => {
    await login(page);

    const uniqueHeadline = `E2E Bangla Publish ${Date.now()}`;

    // 1. Create the Bangla story via the wizard with the language toggle.
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');

    // Language toggle — selector picked defensively.
    const langSelect = page.locator('select[name*="language"], select#language, [wire\\:model\\.live="language"]').first();
    if (await langSelect.isVisible().catch(() => false)) {
      await langSelect.selectOption('bn');
      await page.waitForTimeout(300);
    }

    await page.fill('#headlineInput', uniqueHeadline);
    await page.fill('#briefInput', 'Bangla dispatch.');
    await page.click('.stp[data-go="3"]');
    await page.locator('#catSelect').selectOption({ index: 1 });
    await page.click('#nextBtn');
    await page.waitForTimeout(400);

    // Add an internal note before publishing.
    const noteInput = page.locator('#ntInput');
    if (await noteInput.isVisible().catch(() => false)) {
      await noteInput.fill(NEWSROOM_NOTE);
      await page.locator('#ntSend').click();
      await page.waitForTimeout(400);
    }

    await page.click('#publishBtn');
    await page.waitForTimeout(1500);
    await expect(page.locator('#successCard')).toHaveClass(/show/);

    // 2. Bangla list page shows the headline.
    await page.goto('/admin/news/bn');
    await expect(page.locator(`text=${uniqueHeadline}`).first()).toBeVisible({ timeout: 10000 });

    // 3. Bangla feed (use seeded Prothom Alo client — bn-only package).
    const feed = await request.get('http://localhost:8000/api/v1/portal/feed?language=bn&limit=50');
    expect(feed.ok()).toBeTruthy();
    const j = await feed.json();
    const stories = (j.data ?? []).filter((s: any) => s.language === 'bn');
    expect(stories.some((s: any) => s.headline === uniqueHeadline)).toBeTruthy();

    // 4. The internal note string must NOT appear in any client-facing payload.
    const allClientText = JSON.stringify(j);
    expect(allClientText).not.toContain(NEWSROOM_NOTE);

    // 5. /api/v1/portal/story/{publicId} must also exclude the note.
    const story = stories.find((s: any) => s.headline === uniqueHeadline);
    expect(story?.public_id).toBeTruthy();
    const storyRes = await request.get(`http://localhost:8000/api/v1/portal/story/${story.public_id}`);
    expect(storyRes.ok()).toBeTruthy();
    const storyJson = JSON.stringify(await storyRes.json());
    expect(storyJson).not.toContain(NEWSROOM_NOTE);

    // 6. The internal-note row exists in the DB.
    const noteCount = execSync(
      `php artisan tinker --execute="echo \\App\\Models\\StoryNote::where('body', '${NEWSROOM_NOTE}')->count();"`,
      { stdio: 'pipe' }
    ).toString().trim();
    expect(Number(noteCount)).toBe(1);
  });
});