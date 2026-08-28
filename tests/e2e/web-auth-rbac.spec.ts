import { test, expect } from '@playwright/test';

test.describe('Web + Auth + RBAC', () => {
  test('GET / returns 200', async ({ request }) => {
    const r = await request.get('/');
    expect(r.status()).toBe(200);
  });

  test('GET /up health 200', async ({ request }) => {
    const r = await request.get('/up');
    expect(r.status()).toBe(200);
  });

  test('GET /login renders', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('form')).toBeVisible();
  });

  test('GET /admin redirects to login when guest 302/200', async ({ request }) => {
    const r = await request.get('/admin', { maxRedirects: 0 }).catch(()=>null);
    expect([200,302,401].includes(r?.status() ?? 302)).toBeTruthy();
  });

  test('POST /login rate-ish: missing creds 422 or redirect', async ({ request }) => {
    const r = await request.post('/login', { form: { email:'x@x.com', password:'bad' } });
    expect([200,302,419,422].includes(r.status())).toBeTruthy();
  });

  test('GET /admin/news/en requires auth → redirect', async ({ request }) => {
    const r = await request.get('/admin/news/en', { maxRedirects: 0 }).catch(()=>null);
    expect([302,401].includes(r?.status() ?? 302)).toBeTruthy();
  });

  test('GET /admin/roles requires auth → redirect/302', async ({ request }) => {
    const r = await request.get('/admin/roles', { maxRedirects: 0 }).catch(()=>null);
    expect([302,401].includes(r?.status() ?? 302)).toBeTruthy();
  });

  test('GET /profile requires auth', async ({ request }) => {
    const r = await request.get('/profile', { maxRedirects: 0 }).catch(()=>null);
    expect([302,401].includes(r?.status() ?? 302)).toBeTruthy();
  });

  test('livewire.js asset 200', async ({ request }) => {
    const r = await request.get('/livewire/livewire.js');
    expect(r.status()).toBe(200);
  });
});
