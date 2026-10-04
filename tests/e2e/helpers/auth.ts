import { Page, expect } from '@playwright/test';

export const USERS = {
  admin: { email: 'test@example.com', password: 'password' },
  superadmin: { email: 'nahar@unbnews.org', password: 'password' },
  regularAdmin: { email: 'test@example.com', password: 'password' },
  editor: { email: 'shohel@unbnews.org', password: 'password' },
  biz: { email: 'arif@unbnews.org', password: 'password' },
  photographer: { email: 'mim@unbnews.org', password: 'password' },
} as const;

export const CLIENTS = {
  dailyStar: { email: 'newsdesk@thedailystar.net', password: 'password' },
  prothomAlo: { email: 'cne@prothomalo.com', password: 'password' },
} as const;

export type UserType = keyof typeof USERS;
export type ClientType = keyof typeof CLIENTS;

export async function loginAs(page: Page, userType: UserType) {
  const user = USERS[userType];
  await page.goto('/login');
  await page.fill('input[name="email"]', user.email);
  await page.fill('input[name="password"]', user.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**', { timeout: 10000 });
  await expect(page).not.toHaveURL(/\/login/);
}

export async function loginAsClient(page: Page, clientType: ClientType) {
  const client = CLIENTS[clientType];
  await page.goto('http://localhost:3000/');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);

  const loginBtn = page.locator('button:has-text("Login")').first();
  if (await loginBtn.isVisible().catch(() => false)) {
    await loginBtn.click();
    await page.waitForTimeout(500);
  }

  const emailInput = page.locator('input[type="email"]').first();
  await emailInput.fill(client.email);

  const pwInput = page.locator('input[type="password"]').first();
  await pwInput.fill(client.password);

  const submitBtn = page.locator('button[type="submit"]:has-text("Login")').first();
  await submitBtn.click();

  await page.waitForFunction(
    () => sessionStorage.getItem('unb_portal_token') !== null,
    null,
    { timeout: 15000 }
  ).catch(() => null);

  const hasToken = await page.evaluate(() => {
    return sessionStorage.getItem('unb_portal_token') !== null;
  }).catch(() => false);

  if (!hasToken) {
    throw new Error(`loginAsClient(${clientType}) failed: portal token missing in sessionStorage`);
  }
}

export async function waitForToast(page: Page, text?: string) {
  const toast = page.locator('.toast, [wire\\:toast], [class*="toast"]').first();
  await expect(toast).toBeVisible({ timeout: 5000 });
  if (text) {
    await expect(toast).toContainText(text);
  }
}
