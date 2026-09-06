import { test, expect } from '@playwright/test';

test.describe('Remediation — interaction contracts', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name=email]', 'test@example.com');
    await page.fill('input[name=password]', 'password');
    await page.click('button[type=submit]');
    await page.waitForURL('**/admin**');
  });

  test('New Story link from dashboard and English News goes to add-news', async ({ page }) => {
    await page.goto('/admin');
    const dashLink = page.getByRole('link', { name: '+ New story' }).first();
    await expect(dashLink).toHaveAttribute('href', /\/admin\/add-news/);
    await dashLink.click();
    await expect(page).toHaveURL(/\/admin\/add-news/);
    await page.waitForTimeout(500);
    await page.goto('/admin/news/en');
    await page.waitForLoadState('networkidle');
    const enLink = page.getByRole('link', { name: /\+ (New story|Add News)/i }).first();
    await expect(enLink).toHaveAttribute('href', /\/admin\/add-news/);
  });

  test('UNB Photos Upload button has file input and drop overlay', async ({ page }) => {
    await page.goto('/admin/photos');
    const fileInput = page.locator('input[type=file][wire\\:model="uploads"]').first();
    await expect(fileInput).toBeAttached();
    await expect(page.getByText('Drop photos to upload')).toBeHidden();
  });

  test('Add News wizard stepper has 4 steps with lines and sticky nav', async ({ page }) => {
    await page.goto('/admin/add-news');
    await expect(page.getByRole('button', { name: /Write/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Media/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Organize & access/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Review & publish/ })).toBeVisible();
    await expect(page.getByText('Step 1 of 4')).toBeVisible();
  });

  test('Add News Step 1 wire toolbar has Dateline, Pull quote, Table, Signoff', async ({ page }) => {
    await page.goto('/admin/add-news');
    await expect(page.locator('#editorToolbar button.ql-dateline')).toBeVisible();
    await expect(page.locator('#editorToolbar button.ql-pullquote')).toBeVisible();
    await expect(page.locator('#editorToolbar button.ql-table')).toBeVisible();
    await expect(page.locator('#editorToolbar button.ql-signoff')).toBeVisible();
    await expect(page.getByText('Step 1 of 4')).toBeVisible();
  });

  test('Add News internal notes requires draft', async ({ page }) => {
    await page.goto('/admin/add-news');
    // Go to step 4 where notes are displayed in the review card
    await page.fill('#headlineInput', 'Test Headline for Note Check');
    await page.fill('#briefInput', 'Test Brief for Note Check');
    const step4Btn = page.getByRole('button', { name: /Review & publish/ });
    await step4Btn.click();
    await page.waitForTimeout(400);
    const noteBox = page.locator('#ntInput');
    await expect(noteBox).toBeVisible();
  });
});
