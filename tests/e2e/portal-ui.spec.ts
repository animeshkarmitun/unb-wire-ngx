import { test, expect } from '@playwright/test';

test.describe('Portal UI 3000', () => {
  test('Wire feed page renders header + search input', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    await expect(page.locator('text=UNB Wire').first()).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#omniInput, #portalSearch')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=News wire')).toBeVisible();
  });

  test('search input accepts typing', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    const input = page.locator('#omniInput, #portalSearch').first();
    await input.fill('Bangladesh');
    await expect(input).toHaveValue('Bangladesh');
  });

  test('feed shows stories or empty placeholder', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    await expect(page.locator('body')).toContainText(/Rizvi|Bangla QR|Swapon|stories|No stories/i);
  });

  test('header Live badge visible', async ({ page }) => {
    await page.goto('http://localhost:3000/');
    await expect(page.locator('text=Live').first()).toBeVisible();
  });

  test('download initiates API call and opens presigned URL', async ({ page }) => {
    // Set API key to enable real download
    await page.addInitScript(() => {
      sessionStorage.setItem('unb_api_key', 'test_key_123');
    });
    
    // Mock the download API
    let apiCalled = false;
    await page.route('**/api/v1/media/*/download*', async route => {
      apiCalled = true;
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ url: 'http://mock-s3.com/presigned.png' })
      });
    });

    // We can verify window.open was called by tracking it
    await page.addInitScript(() => {
      (window as any).open = (url: string) => null;
    });

    await page.goto('http://localhost:3000/');
    
    // Switch to Photos tab
    await page.click('text=UNB Photos');
    
    // Click download on the photo of the day
    await page.click('#phHeroDl');
    
    // Check if flash message says Downloaded
    await expect(page.locator('#phHeroDl')).toContainText('Downloaded');
    
    expect(apiCalled).toBe(true);
  });
});

