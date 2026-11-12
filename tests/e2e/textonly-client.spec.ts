import { test, expect } from '@playwright/test';
import path from 'path';

test('Text-only client journey verification on live staging', async ({ page }) => {
  console.log('Navigating to Client Portal http://147.93.171.193:3000...');
  await page.goto('http://147.93.171.193:3000', { waitUntil: 'networkidle' });

  // Direct login on gate screen
  console.log('Filling login credentials on gate screen: tribune@client.unb.com.bd...');
  await page.fill('input[type="email"]', 'tribune@client.unb.com.bd');
  await page.fill('input[type="password"]', 'password');
  
  console.log('Submitting login form...');
  await page.click('button[type="submit"]:has-text("Sign In to Wire Feed")');

  // Wait for authenticated page to load
  await page.waitForTimeout(3000);

  // Verify client name in header
  const userBtn = page.locator('button:has-text("The Daily Tribune")').first();
  await expect(userBtn).toBeVisible({ timeout: 15000 });
  console.log('Client successfully authenticated and displayed on header.');

  // Screenshot 1: Logged in news wire feed
  const feedPath = path.resolve('C:/Users/WEB/.gemini/antigravity/brain/c006f3f3-066c-4d28-87d5-8513590855c1/textonly_client_feed.png');
  await page.screenshot({ path: feedPath });
  console.log('Saved textonly_client_feed.png');

  // Open user dropdown to inspect quota meters
  await userBtn.click();
  await page.waitForTimeout(1000);

  // Screenshot 2: Dropdown showing Wire quota vs Media quota (0/0)
  const quotaPath = path.resolve('C:/Users/WEB/.gemini/antigravity/brain/c006f3f3-066c-4d28-87d5-8513590855c1/textonly_client_quota.png');
  await page.screenshot({ path: quotaPath });
  console.log('Saved textonly_client_quota.png');

  // Close dropdown by clicking header
  await userBtn.click();
  await page.waitForTimeout(500);

  // Switch to UNB Photos tab
  const photosTab = page.locator('button:has-text("UNB Photos")').first();
  if (await photosTab.isVisible()) {
    await photosTab.click();
    await page.waitForTimeout(2000);

    const photosPath = path.resolve('C:/Users/WEB/.gemini/antigravity/brain/c006f3f3-066c-4d28-87d5-8513590855c1/textonly_client_photos_tab.png');
    await page.screenshot({ path: photosPath });
    console.log('Saved textonly_client_photos_tab.png');
  }
});
