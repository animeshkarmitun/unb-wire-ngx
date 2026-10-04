import { test, expect, Page, BrowserContext } from '@playwright/test';
import { execSync } from 'child_process';

/**
 * E2E: two real browser contexts (Admin and Editor) edit the same story.
 * The second save with a stale `version` does NOT overwrite the first save's
 * headline — the DB/API shows the first context's value. The conflict UI
 * appears on the second context.
 */

const ADMIN = { email: 'test@example.com', password: 'password' };
const EDITOR = { email: 'shohel@unbnews.org', password: 'password' };

async function login(page: Page, user: { email: string; password: string }) {
  await page.goto('/login');
  await page.fill('input[name="email"]', user.email);
  await page.fill('input[name="password"]', user.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**', { timeout: 10000 });
}

async function seedStory(headline: string): Promise<string> {
  return execSync(
    `php artisan tinker --execute="\\$u = \\App\\Models\\User::first(); \\$c = \\App\\Models\\Category::first(); \\$s = app(\\App\\Services\\StoryService::class)->createDraft(['language' => 'en', 'headline' => '${headline.replace(/'/g, "\\'")}', 'brief' => 'b', 'body_html' => '<p>x</p>', 'category_id' => \\$c->id], \\$u); app(\\App\\Services\\StoryService::class)->transition(\\$s, 'in_review', \\$u); echo \\$s->public_id;"`,
    { stdio: 'pipe' }
  ).toString().trim();
}

async function openEditor(page: Page, publicId: string): Promise<void> {
  await page.goto(`/admin/story/${publicId}`);
  await page.waitForLoadState('networkidle');
}

test.describe('Two-Browser Stale Save E2E', () => {
  test.setTimeout(90000);

  test('stale save by second context does not overwrite first save; conflict UI shown', async ({ browser }) => {
    const headline = `E2E Two-Browser ${Date.now()}`;
    const publicId = await seedStory(headline);

    const ctxA = await browser.newContext();
    const ctxB = await browser.newContext();
    try {
      const pageA = await ctxA.newPage();
      const pageB = await ctxB.newPage();

      // Both users open the same story. Admin and Editor both can edit.
      await login(pageA, ADMIN);
      await login(pageB, EDITOR);

      await openEditor(pageA, publicId);
      await openEditor(pageB, publicId);

      // Each page has an Edit button — open the wizard and wait for it to load.
      await pageA.locator('a:has-text("Edit"), button:has-text("Edit")').first().click();
      await pageB.locator('a:has-text("Edit"), button:has-text("Edit")').first().click();

      await pageA.waitForLoadState('networkidle');
      await pageB.waitForLoadState('networkidle');

      // Context A saves first.
      const headlineA = `${headline} (A saved)`;
      await pageA.locator('#headlineInput').fill(headlineA);
      await pageA.locator('button:has-text("Save"), button:has-text("Update")').first().click();
      await pageA.waitForTimeout(1500);

      // Context B saves with stale `version` (the UI passes whatever it has).
      const headlineB = `${headline} (B saved)`;
      await pageB.locator('#headlineInput').fill(headlineB);
      await pageB.locator('button:has-text("Save"), button:has-text("Update")').first().click();
      await pageB.waitForTimeout(2500);

      // Conflict UI is shown to context B.
      const conflictText = await pageB.locator('body').textContent();
      expect(conflictText?.toLowerCase()).toMatch(/(conflict|stale|version|merge|409|reload)/);

      // DB/API still holds context A's headline.
      const stored = execSync(
        `php artisan tinker --execute="echo \\App\\Models\\Story::where('public_id', '${publicId}')->value('headline');"`,
        { stdio: 'pipe' }
      ).toString().trim();
      expect(stored).toBe(headlineA);
      expect(stored).not.toBe(headlineB);
    } finally {
      await ctxA.close();
      await ctxB.close();
    }
  });
});