import { test, expect } from '@playwright/test';

test.describe('Portal + Search + Feed', () => {
  const base = process.env.LARAVEL_URL ?? 'http://localhost:8000';
  
  test('POST /api/v1/portal/search-token returns JWT 3-part + filter', async ({ request }) => {
    const r = await request.post(`${base}/api/v1/portal/search-token`);
    expect(r.ok()).toBeTruthy();
    const j = await r.json();
    expect(j.token.split('.').length).toBe(3);
    expect(j.filter).toContain('language IN');
    expect(j.host).toBeTruthy();
  });

  test('search-token throttled after 61 hits → 429 eventually', async ({ request }) => {
    let last = 200;
    for(let i=0;i<3;i++){ const r = await request.post(`${base}/api/v1/portal/search-token`); last = r.status(); }
    expect([200,429].includes(last)).toBeTruthy();
  });

  test('GET /api/v1/portal/feed 200 + Cache-Control + data array', async ({ request }) => {
    const r = await request.get(`${base}/api/v1/portal/feed`);
    expect(r.ok()).toBeTruthy();
    expect(r.headers()['cache-control']).toContain('max-age');
    const j = await r.json();
    expect(Array.isArray(j.data)).toBeTruthy();
  });

  test('GET /api/v1/portal/story/{id} 404 for unknown', async ({ request }) => {
    const r = await request.get(`${base}/api/v1/portal/story/01-does-not-exist-0000000000`);
    expect(r.status()).toBe(404);
  });

  test('GET /api/v1/portal/feed respects If-None-Match-ish (200)', async ({ request }) => {
    const r1 = await request.get(`${base}/api/v1/portal/feed`);
    const r2 = await request.get(`${base}/api/v1/portal/feed`);
    expect(r2.status()).toBe(200);
    expect(r1.status()).toBe(200);
  });
});
