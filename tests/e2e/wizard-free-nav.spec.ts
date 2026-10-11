import { test, expect } from '@playwright/test';

const LARAVEL = process.env.LARAVEL_URL ?? 'http://localhost:8000';

test('stepper navigates freely + live preview starts collapsed', async ({ page }) => {
  test.setTimeout(45000);
  await page.goto(`${LARAVEL}/login`);
  await page.fill('input[name="email"]', 'test@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**', { timeout: 10000 });
  await page.goto(`${LARAVEL}/admin/add-news`);
  await page.waitForLoadState('networkidle');

  // 1. Preview starts collapsed on first paint (server-rendered class).
  await expect(page.locator('#addGrid')).toHaveClass(/pv-collapsed/);

  // 2. Click the Media circle with an EMPTY headline — must jump to step 2.
  await page.click('.stp[data-go="2"]');
  await expect(page.locator('.stp.active .stp-label')).toHaveText('Media', { timeout: 5000 });

  // 3. And to step 4 (Review & publish).
  await page.click('.stp[data-go="4"]');
  await expect(page.locator('.stp.active .stp-label')).toHaveText('Review & publish', { timeout: 5000 });

  // 4. The pv strip stays visible while collapsed and the panel is hidden.
  await expect(page.locator('#pvStrip')).toBeVisible();
  await expect(page.locator('#pvBody')).toBeHidden();
});
