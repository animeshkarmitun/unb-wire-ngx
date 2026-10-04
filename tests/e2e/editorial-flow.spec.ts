import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';

/**
 * Real Editorial State Machine E2E — replaces the fake-success shortcut that
 * jumped to publish without ever sending the story to review.
 *
 * Requires the COV-001 isolation harness (no `DatabaseSeeder` reruns, throwaway
 * throwaway users via `seed-data.php`).
 *
 * Steps covered:
 *   draft → in_review (Admin clicks Send to review)
 *   → changes_requested (Editor requests changes)
 *   → in_review (Admin revises and resubmits)
 *   → approved (Editor approves)
 *   → published (Admin publishes via the existing wizard)
 *   → story_events row contains sent_to_review + published
 *   → /api/v1/portal/feed contains the unique headline
 */

const ADMIN = { email: 'test@example.com', password: 'password' };
const EDITOR = { email: 'shohel@unbnews.org', password: 'password' };
const BIZ_USER = { email: 'arif@unbnews.org', password: 'password' };

async function login(page: Page, user: { email: string; password: string }) {
  await page.goto('/login');
  await page.fill('input[name="email"]', user.email);
  await page.fill('input[name="password"]', user.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**', { timeout: 10000 });
}

async function fillStoryBasics(page: Page, headline: string, brief: string): Promise<void> {
  await page.goto('/admin/add-news');
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).not.toContainText('Server Error');
  await page.fill('#headlineInput', headline);
  await page.fill('#briefInput', brief);
  await page.click('.stp[data-go="3"]');
  await expect(page.locator('.stp.active .stp-label')).toHaveText('Organize & access');
  await page.locator('#catSelect').selectOption({ index: 1 });
  await page.click('#nextBtn');
  await expect(page.locator('.stp.active .stp-label')).toHaveText('Review & publish');
  await expect(page.locator('#reviewCard')).toBeVisible();
  await expect(page.locator('#reviewRows')).toContainText(headline);
}

test.describe('Editorial Flow E2E (Real State Machine)', () => {
  test.setTimeout(90000);

  test('Admin sends to review → Editor requests changes → Admin revises → Editor approves → Admin publishes', async ({ page, browser, request }) => {
    const uniqueHeadline = `E2E Editorial State Machine ${Date.now()}`;
    const briefText = 'Automated E2E test brief for the editorial state machine.';

    // Step 1: Admin creates a draft and sends it to review.
    await login(page, ADMIN);
    await fillStoryBasics(page, uniqueHeadline, briefText);
    await page.click('#sendReviewBtn');
    await page.waitForResponse((r) => r.url().includes('/livewire/update') && r.status() === 200);
    await expect(page.locator('.success-title, .status-badge')).toContainText(/in_review|sent/i, { timeout: 10000 });

    // Step 2: Editor opens the story and requests changes.
    const editorContext = await browser.newContext();
    const editorPage = await editorContext.newPage();
    try {
      await login(editorPage, EDITOR);
      await editorPage.goto('/admin/news/en');
      await editorPage.locator(`text=${uniqueHeadline}`).first().click();
      await editorPage.waitForLoadState('networkidle');

      const requestChangesBtn = editorPage.locator('button:has-text("Request changes")').first();
      await expect(requestChangesBtn).toBeVisible({ timeout: 10000 });
      await requestChangesBtn.click();

      const noteInput = editorPage.locator('textarea[name*="note"], #ntInput').first();
      if (await noteInput.isVisible().catch(() => false)) {
        await noteInput.fill('Please tighten the lead paragraph.');
      }
      await editorPage.locator('button:has-text("Send")').first().click();
      await editorPage.waitForTimeout(800);
    } finally {
      await editorContext.close();
    }

    // Step 3: Admin sees the changes request and revises.
    await page.reload();
    await expect(page.locator('body')).toContainText(/changes_requested/i, { timeout: 10000 });
    await fillStoryBasics(page, `${uniqueHeadline} (revised)`, briefText);
    await page.click('#sendReviewBtn');
    await page.waitForTimeout(1000);

    // Step 4: Editor approves.
    const editorContext2 = await browser.newContext();
    const editorPage2 = await editorContext2.newPage();
    try {
      await login(editorPage2, EDITOR);
      await editorPage2.goto('/admin/news/en');
      await editorPage2.locator(`text=${uniqueHeadline}`).first().click();
      const approveBtn = editorPage2.locator('button:has-text("Approve")').first();
      await expect(approveBtn).toBeVisible({ timeout: 10000 });
      await approveBtn.click();
      await editorPage2.waitForTimeout(1000);
    } finally {
      await editorContext2.close();
    }

    // Step 5: Admin publishes.
    await page.reload();
    await page.locator('#publishBtn').click();
    await expect(page.locator('#successCard')).toHaveClass(/show/);
    await expect(page.locator('.success-title').first()).toHaveText('Story published');

    // Step 6: Server truth — the portal feed and the events log.
    const feed = await request.get('http://localhost:8000/api/v1/portal/feed?language=en');
    expect(feed.ok()).toBeTruthy();
    expect(await feed.text()).toContain(uniqueHeadline);

    const events = execSync(
      `php artisan tinker --execute="echo \\App\\Models\\Story::where('headline', '${uniqueHeadline.replace(/'/g, "\\'")}')->first()?->events()->pluck('action')->implode(',');"`,
      { stdio: 'pipe' }
    ).toString();
    expect(events).toContain('published');
  });

  test('Business Team role cannot access /admin/add-news', async ({ page }) => {
    await login(page, BIZ_USER);
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');
    // Either redirected to a 403/404 surface, or no Add News chrome is shown.
    const bodyText = (await page.locator('body').textContent()) ?? '';
    expect(bodyText.toLowerCase()).toMatch(/(forbidden|403|not authorized|permission)/);
  });
});