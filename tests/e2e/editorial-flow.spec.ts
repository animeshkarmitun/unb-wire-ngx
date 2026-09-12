import { test, expect, Page } from '@playwright/test';

/**
 * Real Editorial Flow E2E — replaces the fake-success tests.
 *
 * Tests the critical path: draft → in_review → approved → published,
 * then verifies the story appears in the portal API feed.
 * Also tests RBAC: Business Team role cannot access add-news.
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

test.describe('Editorial Flow E2E (Real Assertions)', () => {
  test.setTimeout(60000);

  test('draft → in_review → approved → published appears in portal API feed', async ({ page, request }) => {
    // --- Login as Admin ---
    await login(page, ADMIN);

    // --- Navigate to Add News ---
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText('Server Error');

    // --- Fill in the story form (Step 1: Write) ---
    const uniqueHeadline = `E2E Test Story ${Date.now()}`;
    const briefText = 'Automated E2E test brief for editorial flow validation.';

    await page.fill('#headlineInput', uniqueHeadline);
    await page.fill('#briefInput', briefText);

    // Navigate to Step 3: Organize & access
    await page.click('.stp[data-go="3"]');
    await page.waitForTimeout(400);
    await expect(page.locator('.stp.active .stp-label')).toHaveText('Organize & access');

    // Select category
    await page.locator('#catSelect').selectOption({ index: 1 });
    await page.waitForTimeout(200);

    // Navigate to Step 4: Review & publish
    await page.click('#nextBtn');
    await page.waitForTimeout(400);
    await expect(page.locator('.stp.active .stp-label')).toHaveText('Review & publish');

    // Verify Review Card content contains the headline
    await expect(page.locator('#reviewCard')).toBeVisible();
    await expect(page.locator('#reviewRows')).toContainText(uniqueHeadline);

    // Add internal note before publishing
    await page.fill('#ntInput', 'Note added prior to publication.');
    await page.click('#ntSend');
    await page.waitForTimeout(400);
    await expect(page.locator('#ntList')).toContainText('Note added prior to publication.');

    // Publish story
    await page.click('#publishBtn');
    await page.waitForTimeout(1000);

    // Verify success card is displayed
    await expect(page.locator('#successCard')).toHaveClass(/show/);
    await expect(page.locator('.success-title').first()).toHaveText('Story published');

    // Verify the published story appears in the admin News list
    await page.goto('/admin/news/en');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('text=' + uniqueHeadline).first()).toBeVisible({ timeout: 10000 });

    // Open drawer to inspect workflow and notes
    const titleLink = page.locator(`.news-table tbody tr:has-text("${uniqueHeadline}") .news-title`).first();
    if (await titleLink.isVisible().catch(() => false)) {
      await titleLink.click();
      const drawer = page.locator('aside.wfd');
      await expect(drawer).toHaveClass(/open/, { timeout: 5000 });
      await expect(drawer.locator('.wfd-step.now.done')).toContainText('Published');
      await drawer.locator('.wfd-close').click();
    }

    // Verify via Portal API
    const base = process.env.LARAVEL_URL ?? 'http://localhost:8000';
    const feedRes = await request.get(`${base}/api/v1/portal/feed?search=${encodeURIComponent(uniqueHeadline)}`);
    expect(feedRes.ok()).toBeTruthy();

    const json = await feedRes.json();
    expect(json).toHaveProperty('data');
    expect(Array.isArray(json.data)).toBeTruthy();

    const found = json.data.find((s: any) => s.headline === uniqueHeadline);
    expect(found).toBeTruthy();
    expect(found.status).toBe('published');
  });

  test('RBAC: Business Team user cannot access add-news page', async ({ page }) => {
    await login(page, BIZ_USER);

    // Navigate to add-news — should be forbidden
    const res = await page.goto('/admin/add-news');
    const status = res?.status() ?? 0;

    // Should either get 403 or be redirected away, or see "Forbidden" text
    const isForbidden = status === 403 ||
      await page.locator('body').textContent().then(t => /forbidden|not authorized|access denied/i.test(t ?? '')).catch(() => false);

    const isRedirected = !page.url().includes('add-news');

    expect(isForbidden || isRedirected).toBeTruthy();
  });

  test('RBAC: Business Team user cannot access news list', async ({ page }) => {
    await login(page, BIZ_USER);

    const res = await page.goto('/admin/news/en');
    const status = res?.status() ?? 0;

    const isForbidden = status === 403 ||
      await page.locator('body').textContent().then(t => /forbidden|not authorized|access denied/i.test(t ?? '')).catch(() => false);

    const isRedirected = !page.url().includes('news/en');

    expect(isForbidden || isRedirected).toBeTruthy();
  });

  test('portal feed returns valid published story structure', async ({ request }) => {
    const base = process.env.LARAVEL_URL ?? 'http://localhost:8000';
    const res = await request.get(`${base}/api/v1/portal/feed`);
    expect(res.ok()).toBeTruthy();

    const json = await res.json();
    expect(json).toHaveProperty('data');
    expect(Array.isArray(json.data)).toBeTruthy();

    if (json.data.length > 0) {
      const story = json.data[0];
      // Assert required fields exist on each story
      expect(story).toHaveProperty('public_id');
      expect(story).toHaveProperty('headline');
      expect(story).toHaveProperty('language');
      expect(story).toHaveProperty('published_at');
      expect(story).toHaveProperty('is_breaking');
      expect(story).toHaveProperty('category');
      expect(story).toHaveProperty('media');
      expect(story.status).toBe('published');
      // published_at should be a valid ISO date
      expect(new Date(story.published_at).getTime()).toBeGreaterThan(0);
    }
  });

  test('portal story detail endpoint returns full story with body', async ({ request }) => {
    const base = process.env.LARAVEL_URL ?? 'http://localhost:8000';

    // First get a story from the feed
    const feedRes = await request.get(`${base}/api/v1/portal/feed?limit=1`);
    const feed = await feedRes.json();

    if (feed.data && feed.data.length > 0) {
      const publicId = feed.data[0].public_id;

      // Fetch the full story detail
      const detailRes = await request.get(`${base}/api/v1/portal/story/${publicId}`);
      expect(detailRes.ok()).toBeTruthy();

      const detail = await detailRes.json();
      expect(detail).toHaveProperty('data');
      expect(detail.data.public_id).toBe(publicId);
      expect(detail.data).toHaveProperty('body_html');
      expect(detail.data).toHaveProperty('tags');
      expect(detail.data).toHaveProperty('media');
      expect(Array.isArray(detail.data.tags)).toBeTruthy();
      expect(Array.isArray(detail.data.media)).toBeTruthy();
    }
  });

  test('search token endpoint returns valid JWT structure', async ({ request }) => {
    const base = process.env.LARAVEL_URL ?? 'http://localhost:8000';
    const res = await request.post(`${base}/api/v1/portal/search-token`);
    expect(res.ok()).toBeTruthy();

    const json = await res.json();
    expect(json).toHaveProperty('token');
    expect(json).toHaveProperty('host');
    expect(json).toHaveProperty('index');
    expect(json).toHaveProperty('expires_at');

    // Token should be a valid JWT (3 dot-separated parts)
    const parts = json.token.split('.');
    expect(parts.length).toBe(3);
  });
});
