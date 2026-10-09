import { test } from '@playwright/test';

test('photo manager drag-drop overlay: hidden by default, closable via ✕ / Esc / backdrop', async ({ page }) => {
  test.setTimeout(45000);

  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="email"]', 'test@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**', { timeout: 10000 });
  await page.goto('http://localhost:8000/admin/photos');
  await page.waitForLoadState('networkidle');

  const overlay = page.locator('.dam-drop');

  // 1. Hidden by default (no hardcoded .show).
  if (!(await overlay.isHidden())) throw new Error('overlay visible before any drag — hardcoded .show is back or x-show broken');
  console.log('V1 overlay hidden by default: true');

  // 2. Synthesize a window dragenter — overlay appears.
  await page.evaluate(() => window.dispatchEvent(new DragEvent('dragenter', { bubbles: true })));
  await page.waitForTimeout(300);
  if (!(await overlay.isVisible())) throw new Error('overlay did not appear on dragenter');
  const closeVisible = await page.locator('.dam-drop-close').isVisible();
  if (!closeVisible) throw new Error('close button not visible inside overlay');
  console.log('V2 overlay opens on dragenter + close button visible: true');

  // 3. Esc closes it.
  await page.keyboard.press('Escape');
  await page.waitForTimeout(300);
  if (!(await overlay.isHidden())) throw new Error('Escape did not close the overlay');
  console.log('V3 Escape closes overlay: true');

  // 4. Reopen, click the ✕ button.
  await page.evaluate(() => window.dispatchEvent(new DragEvent('dragenter', { bubbles: true })));
  await page.waitForTimeout(300);
  await page.locator('.dam-drop-close').click();
  await page.waitForTimeout(300);
  if (!(await overlay.isHidden())) throw new Error('✕ button did not close the overlay');
  console.log('V4 ✕ closes overlay: true');

  // 5. Reopen, click the backdrop (outside the box) — @click.self closes.
  await page.evaluate(() => window.dispatchEvent(new DragEvent('dragenter', { bubbles: true })));
  await page.waitForTimeout(300);
  await page.locator('.dam-drop').click({ position: { x: 40, y: 40 } });
  await page.waitForTimeout(300);
  if (!(await overlay.isHidden())) throw new Error('backdrop click did not close the overlay');
  console.log('V5 backdrop click closes overlay: true');

  // 6. Dragleave leaving the window (relatedTarget null) resets dragDepth to 0.
  await page.evaluate(() => window.dispatchEvent(new DragEvent('dragenter', { bubbles: true })));
  await page.evaluate(() => window.dispatchEvent(new DragEvent('dragenter', { bubbles: true })));
  await page.evaluate(() => window.dispatchEvent(new DragEvent('dragleave', { bubbles: true }))); // relatedTarget null
  await page.waitForTimeout(300);
  if (!(await overlay.isHidden())) throw new Error('window-leave dragleave did not reset dragDepth');
  console.log('V6 window-leave resets dragDepth: true');

  console.log('ALL DRAG-DROP OVERLAY CHECKS PASSED');
});