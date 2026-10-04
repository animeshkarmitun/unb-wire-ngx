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

  test('search-token rate limit at 60 per minute hits 429 within 3 extra calls', async ({ request }) => {
    // Issue 60 successful calls first, then assert the next call is 429.
    for (let i = 0; i < 60; i++) {
      await request.post(`${base}/api/v1/portal/search-token`);
    }
    const blocked = await request.post(`${base}/api/v1/portal/search-token`);
    expect(blocked.status()).toBe(429);
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

  test('GET /api/v1/portal/feed responds 200 on two consecutive requests', async ({ request }) => {
    const r1 = await request.get(`${base}/api/v1/portal/feed`);
    const r2 = await request.get(`${base}/api/v1/portal/feed`);
    expect(r1.status()).toBe(200);
    expect(r2.status()).toBe(200);
  });
});
