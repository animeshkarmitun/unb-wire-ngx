import { test, expect } from '@playwright/test';

test.describe('Client API + Media + Billing', () => {
  const base = process.env.LARAVEL_URL ?? 'http://localhost:8000';

  test('GET /api/v1/feed without key → 401', async ({ request }) => {
    const r = await request.get(`${base}/api/v1/feed`);
    expect(r.status()).toBe(401);
  });

  test('GET /api/v1/feed with bogus key → 401', async ({ request }) => {
    const r = await request.get(`${base}/api/v1/feed`, { headers: { Authorization: 'Bearer bogus' } });
    expect(r.status()).toBe(401);
  });

  test('X-API-Key header with invalid key returns 401 Unauthorized', async ({ request }) => {
    const r = await request.get(`${base}/api/v1/feed`, { headers: { 'X-API-Key': 'not-a-real-key' } });
    expect(r.status()).toBe(401);
  });

  test('POST /api/uploads without auth returns 401', async ({ request }) => {
    const r = await request.post(`${base}/api/uploads`, { data: { upload_length: 10 }, headers: { Accept: 'application/json' } });
    expect(r.status()).toBe(401);
  });

  test('GET /api/media/{id}/presigned without auth returns 401', async ({ request }) => {
    const r = await request.get(`${base}/api/media/01-fake-000000000000000000/presigned`, { headers: { Accept: 'application/json' } });
    expect(r.status()).toBe(401);
  });

  test('POST /api/ai/headline without auth returns 401', async ({ request }) => {
    const r = await request.post(`${base}/api/ai/headline`, { data: { text: 'test' }, headers: { Accept: 'application/json' } });
    expect(r.status()).toBe(401);
  });

  test('GET /up health check returns 200', async ({ request }) => {
    const r = await request.get(`${base}/up`);
    expect(r.status()).toBe(200);
  });
});

test.describe('Portal Client API Contract & Entitlements', () => {
  const base = process.env.LARAVEL_URL ?? 'http://localhost:8000';

  test('GET /api/v1/portal/context returns 200 with client context contract', async ({ request }) => {
    const r = await request.get(`${base}/api/v1/portal/context`);
    expect(r.ok()).toBeTruthy();
    const json = await r.json();
    expect(json).toHaveProperty('client');
    expect(json).toHaveProperty('saved_searches');
  });

  test('POST /api/v1/portal/search-token returns valid JWT structure and filter', async ({ request }) => {
    const r = await request.post(`${base}/api/v1/portal/search-token`);
    expect(r.ok()).toBeTruthy();
    const json = await r.json();
    expect(json).toHaveProperty('token');
    expect(json.token.split('.').length).toBe(3);
    expect(json).toHaveProperty('host');
  });
});

