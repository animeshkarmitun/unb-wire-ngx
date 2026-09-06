import { test, expect } from '@playwright/test';

test.describe('Story Reader Faithful (M8-STORY-001 / story.html + FR-NWS-019)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 }).catch(() => {});
  });

  test('Navigates from news list to faithful story reader view', async ({ page }) => {
    await page.goto('/admin/news/en');

    // Find first story reader link
    const firstStoryLink = page.locator('a.story-view-link, a[href*="/admin/story/"]').first();
    await expect(firstStoryLink).toBeVisible({ timeout: 10000 });
    const targetUrl = await firstStoryLink.getAttribute('href');
    expect(targetUrl).toBeTruthy();

    await page.goto(targetUrl!);

    // Story reader root container
    const readerRoot = page.locator('.story-reader-root');
    await expect(readerRoot).toBeVisible({ timeout: 10000 });

    // 1. Sub-masthead bar with back navigation & live Dhaka clock
    await expect(page.locator('.story-subhead-bar')).toBeVisible();
    await expect(page.locator('.story-back-link')).toBeVisible();
    await expect(page.locator('.live-clock-badge')).toBeVisible();
    await expect(page.locator('#readerClockTime')).toContainText(/Dhaka/i);

    // 2. Breadcrumb
    await expect(page.locator('.story-breadcrumb')).toBeVisible();

    // 3. Action Buttons Bar
    const actionsBar = page.locator('.story-actions-bar');
    await expect(actionsBar).toBeVisible();
    await expect(page.locator('#dlWord')).toContainText('Download Word');
    await expect(page.locator('#dlText')).toContainText('Download Text');
    await expect(page.locator('#dlImage')).toContainText('Download Image');
    await expect(page.locator('#dlXml')).toContainText('Download XML');
    await expect(page.locator('#printBtn')).toContainText('Print');
    await expect(page.locator('#copyDispatch')).toContainText('Copy Dispatch');
    await expect(actionsBar.locator('a[href*="/admin/add-news"]')).toBeVisible();

    // 4. Headline, Category, Byline
    await expect(page.locator('.story-category-pill')).toBeVisible();
    await expect(page.locator('#storyHead')).toBeVisible();
    await expect(page.locator('.story-byline-bar')).toBeVisible();
    await expect(page.locator('#storyDate')).toBeVisible();

    // 5. Featured Media & Caption
    await expect(page.locator('.story-featured-box')).toBeVisible();
    await expect(page.locator('.story-featured-caption')).toBeVisible();

    // 6. Body & Signoff
    const storyBody = page.locator('#storyBody');
    await expect(storyBody).toBeVisible();
    await expect(page.locator('.story-signoff-line')).toContainText(/END\/UNB/i);

    // 7. Right column Newsroom Control Panel (FR-NWS-019)
    await expect(page.locator('.story-editorial-panel')).toBeVisible();
    await expect(page.locator('.story-editorial-title')).toContainText(/Newsroom Control/i);
    await expect(page.locator('text=Wire Public ID:')).toBeVisible();
    await expect(page.locator('text=Language / Desk:')).toBeVisible();

    // 8. Latest wire news rail
    await expect(page.locator('.story-wire-rail')).toBeVisible();
    await expect(page.locator('.story-rail-header')).toContainText(/Latest wire news/i);

    // 9. Related Articles
    await expect(page.locator('.story-related-section')).toBeVisible();
    await expect(page.locator('.story-section-header')).toContainText(/Related articles/i);
  });

  test('Action buttons trigger wire export and flash feedback', async ({ page }) => {
    await page.goto('/admin/news/en');
    const firstStoryLink = page.locator('a.story-view-link, a[href*="/admin/story/"]').first();
    await expect(firstStoryLink).toBeVisible({ timeout: 10000 });
    const href = await firstStoryLink.getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    await expect(page.locator('.story-reader-root')).toBeVisible({ timeout: 10000 });

    // Test Word download button feedback
    const wordBtn = page.locator('#dlWord');
    await wordBtn.click();
    await expect(wordBtn).toHaveClass(/done/, { timeout: 3000 });
    await expect(wordBtn).toContainText('Word saved');

    // Test Text download button feedback
    const textBtn = page.locator('#dlText');
    await textBtn.click();
    await expect(textBtn).toHaveClass(/done/, { timeout: 3000 });
    await expect(textBtn).toContainText('Text saved');

    // Test XML download button feedback
    const xmlBtn = page.locator('#dlXml');
    await xmlBtn.click();
    await expect(xmlBtn).toHaveClass(/done/, { timeout: 3000 });
    await expect(xmlBtn).toContainText('XML saved');

    // Test Image download button feedback
    const imgBtn = page.locator('#dlImage');
    await imgBtn.click();
    await expect(imgBtn).toHaveClass(/done/, { timeout: 3000 });
    await expect(imgBtn).toContainText('Image saved');

    // Test Copy Dispatch button feedback
    const dispatchBtn = page.locator('#copyDispatch');
    await dispatchBtn.click();
    await expect(dispatchBtn).toHaveClass(/done/, { timeout: 3000 });
    await expect(dispatchBtn).toContainText('Dispatch copied');
  });

  test('Internal newsroom note can be submitted and rendered in thread', async ({ page }) => {
    await page.goto('/admin/news/en');
    const firstStoryLink = page.locator('a.story-view-link, a[href*="/admin/story/"]').first();
    await expect(firstStoryLink).toBeVisible({ timeout: 10000 });
    const href = await firstStoryLink.getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    await expect(page.locator('.story-reader-root')).toBeVisible({ timeout: 10000 });

    const testNoteText = 'Verified field report from bureau chief at ' + Date.now();
    const noteTextarea = page.locator('textarea[placeholder*="confidential editorial note"]');
    await expect(noteTextarea).toBeVisible();
    await noteTextarea.fill(testNoteText);

    // Click submit note button
    await page.click('button:has-text("Post Internal Note")');

    // Check toast notification and newly appended note in thread
    await expect(page.locator('#notesFeed')).toContainText(testNoteText, { timeout: 8000 });
  });

  test('Edit in Wizard links correctly to add-news with story id', async ({ page }) => {
    await page.goto('/admin/news/en');
    const firstStoryLink = page.locator('a.story-view-link, a[href*="/admin/story/"]').first();
    await expect(firstStoryLink).toBeVisible({ timeout: 10000 });
    const href = await firstStoryLink.getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    await expect(page.locator('.story-reader-root')).toBeVisible({ timeout: 10000 });

    const editBtn = page.locator('.story-actions-bar a:has-text("Edit in Wizard")');
    await expect(editBtn).toBeVisible();
    const editHref = await editBtn.getAttribute('href');
    expect(editHref).toMatch(/\/admin\/add-news\?id=\d+/);
  });
});
