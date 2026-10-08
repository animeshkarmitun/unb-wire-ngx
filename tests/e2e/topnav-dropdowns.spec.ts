import { test } from '@playwright/test';

/**
 * Regression test for the CSP 'unsafe-eval' bug (2026-10-08).
 *
 * The CSP-nonce refactor shipped script-src without 'unsafe-eval'. Alpine.js
 * (bundled with Livewire) evaluates x-data / x-show / @click expressions via
 * new Function(), so CSP silently killed EVERY Alpine directive on the admin
 * panel: both topnav dropdowns rendered permanently open, clicks did nothing,
 * and the sidebar toggle was dead. This spec fails if that ever regresses —
 * it first asserts zero CSP EvalErrors on the page, then drives the dropdowns.
 */
test('topnav dropdowns: exclusive open + close on outside click (CSP eval regression)', async ({ page }) => {
  test.setTimeout(30000);
  const evalErrors: string[] = [];
  page.on('pageerror', (e) => { if (String(e).includes('EvalError') || String(e).includes('Content Security Policy')) evalErrors.push(String(e).slice(0, 160)); });

  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="email"]', 'test@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**', { timeout: 10000 });
  await page.waitForTimeout(1000);

  if (evalErrors.length) throw new Error('CSP eval errors still present: ' + evalErrors[0]);

  const notifPanel = page.locator('button[aria-label="Notifications"] + div');
  const userPanel = page.locator('button[aria-label="User menu"] ~ div[x-show]');

  // Initially both hidden.
  if (!(await notifPanel.isHidden()) || !(await userPanel.isHidden())) throw new Error('panels visible before any click');

  // 1. Open the notification bell.
  await page.click('button[aria-label="Notifications"]');
  await page.waitForTimeout(300);
  if (!(await notifPanel.isVisible())) throw new Error('notif did not open');
  if (!(await userPanel.isHidden())) throw new Error('user panel open at the same time as notif');

  // 2. Open the user menu — notif must close (shared state).
  await page.click('button[aria-label="User menu"]');
  await page.waitForTimeout(300);
  if (!(await notifPanel.isHidden())) throw new Error('notif did not close when user opened');
  if (!(await userPanel.isVisible())) throw new Error('user did not open');

  // 3. Click outside — user dropdown must close.
  await page.locator('main h1').first().click({ position: { x: 5, y: 5 } });
  await page.waitForTimeout(400);
  if (!(await userPanel.isHidden())) throw new Error('user panel did not close on outside click');

  console.log('ALL DROPDOWN CHECKS PASSED — no CSP eval errors');
});