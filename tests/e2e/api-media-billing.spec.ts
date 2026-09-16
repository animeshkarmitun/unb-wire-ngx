import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

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

test.describe('Wire Feed Delivery Engine, Entitlements & Tombstones (M13-API)', () => {
  const base = process.env.LARAVEL_URL ?? 'http://localhost:8000';

  test.beforeAll(() => {
    execSync('php tests/e2e/helpers/seed-data.php wire_api', { stdio: 'pipe' });
  });

  test('GET /api/v1/feed with valid API key returns 200 with wire feed items and pagination cursor', async ({ request }) => {
    const res = await request.get(`${base}/api/v1/feed`, {
      headers: { Authorization: 'Bearer unb_live_testkey_dailystar_001' },
    });
    expect(res.status()).toBe(200);
    const body = await res.json();
    expect(body).toHaveProperty('data');
    expect(Array.isArray(body.data)).toBe(true);
    expect(body.data.length).toBeGreaterThan(0);
    expect(body).toHaveProperty('cursor');

    const firstItem = body.data[0];
    expect(firstItem).toHaveProperty('public_id');
    expect(firstItem).toHaveProperty('headline');
    expect(firstItem).toHaveProperty('brief');
    expect(firstItem).toHaveProperty('language');
    expect(firstItem).toHaveProperty('published_at');
    expect(firstItem).toHaveProperty('status');
    expect(firstItem).toHaveProperty('is_breaking');
    expect(firstItem).toHaveProperty('is_killed');
  });

  test('FR-DST-002: Declarative entitlement filtering strictly restricts languages and categories', async ({ request }) => {
    // Daily Star is subscribed to PKG-E2E-EN-POL (English + Politics)
    const dsRes = await request.get(`${base}/api/v1/feed`, {
      headers: { Authorization: 'Bearer unb_live_testkey_dailystar_001' },
    });
    expect(dsRes.status()).toBe(200);
    const dsBody = await dsRes.json();
    const dsPublicIds = dsBody.data.map((item: any) => item.public_id);

    // Must include English Politics story
    expect(dsPublicIds).toContain('01JWIREAPITESTENPOL0000001');
    // Must NOT include English Business story (unentitled category)
    expect(dsPublicIds).not.toContain('01JWIREAPITESTENBIZ0000002');
    // Must NOT include Bangla Sports story (unentitled language & category)
    expect(dsPublicIds).not.toContain('01JWIREAPITESTBNSPT0000003');

    // All returned stories must be English
    for (const item of dsBody.data) {
      expect(item.language).toBe('en');
    }

    // Prothom Alo is subscribed to PKG-E2E-BN-ALL (Bangla + all categories)
    const paRes = await request.get(`${base}/api/v1/feed`, {
      headers: { Authorization: 'Bearer unb_live_testkey_prothomalo_005' },
    });
    expect(paRes.status()).toBe(200);
    const paBody = await paRes.json();
    const paPublicIds = paBody.data.map((item: any) => item.public_id);

    // Must include Bangla Sports story
    expect(paPublicIds).toContain('01JWIREAPITESTBNSPT0000003');
    // Must NOT include English Politics story
    expect(paPublicIds).not.toContain('01JWIREAPITESTENPOL0000001');

    // All returned stories must be Bangla
    for (const item of paBody.data) {
      expect(item.language).toBe('bn');
    }
  });

  test('DEC-007: Retraction tombstones deliver killed stories with status=killed and retraction notice', async ({ request }) => {
    const res = await request.get(`${base}/api/v1/feed`, {
      headers: { Authorization: 'Bearer unb_live_testkey_dailystar_001' },
    });
    expect(res.status()).toBe(200);
    const body = await res.json();
    const killedStory = body.data.find((s: any) => s.public_id === '01JWIREAPITESTKILLED000004');

    expect(killedStory).toBeDefined();
    expect(killedStory.status).toBe('killed');
    expect(killedStory.is_killed).toBe(true);
    expect(killedStory.brief).toBe('STORY KILLED / RETRACTED');
    expect(killedStory.killed_at).not.toBeNull();
  });
});

test.describe('Wire Story & Media Download Ledgering & Access Controls (M13-API)', () => {
  const base = process.env.LARAVEL_URL ?? 'http://localhost:8000';

  test.beforeAll(() => {
    execSync('php tests/e2e/helpers/seed-data.php wire_api', { stdio: 'pipe' });
  });

  test('GET /api/v1/story/{publicId}/download delivers json-unb-v1 format and ledgers download in DB', async ({ request }) => {
    const initialCount = parseInt(execSync('php tests/e2e/helpers/seed-data.php download_count story').toString().trim(), 10);

    const res = await request.get(`${base}/api/v1/story/01JWIREAPITESTENPOL0000001/download?format=json-unb-v1`, {
      headers: { Authorization: 'Bearer unb_live_testkey_dailystar_001' },
    });
    expect(res.status()).toBe(200);
    expect(res.headers()['content-type']).toContain('application/json');
    expect(res.headers()['content-disposition']).toContain('attachment; filename="UNB-01JWIREAPITESTENPOL0000001.json"');

    const json = await res.json();
    expect(json.public_id).toBe('01JWIREAPITESTENPOL0000001');
    expect(json.headline).toContain('Parliament approves');

    // Verify download record inserted
    const newCount = parseInt(execSync('php tests/e2e/helpers/seed-data.php download_count story').toString().trim(), 10);
    expect(newCount).toBeGreaterThan(initialCount);
  });

  test('GET /api/v1/story/{publicId}/download delivers newsml-g2 and nitf XML formats', async ({ request }) => {
    // NewsML-G2
    const g2Res = await request.get(`${base}/api/v1/story/01JWIREAPITESTENPOL0000001/download?format=newsml-g2`, {
      headers: { Authorization: 'Bearer unb_live_testkey_dailystar_001' },
    });
    expect(g2Res.status()).toBe(200);
    expect(g2Res.headers()['content-disposition']).toContain('attachment; filename="UNB-01JWIREAPITESTENPOL0000001.xml"');
    const g2Text = await g2Res.text();
    expect(g2Text).toContain('<newsItem');
    expect(g2Text).toContain('standard="NewsML-G2"');

    // NITF
    const nitfRes = await request.get(`${base}/api/v1/story/01JWIREAPITESTENPOL0000001/download?format=nitf`, {
      headers: { Authorization: 'Bearer unb_live_testkey_dailystar_001' },
    });
    expect(nitfRes.status()).toBe(200);
    expect(nitfRes.headers()['content-disposition']).toContain('attachment; filename="UNB-01JWIREAPITESTENPOL0000001.nitf.xml"');
    const nitfText = await nitfRes.text();
    expect(nitfText).toContain('<nitf');
  });

  test('Story download gate rejects unentitled stories with 403 Forbidden', async ({ request }) => {
    // Daily Star (English only) requests Bangla story download
    const res = await request.get(`${base}/api/v1/story/01JWIREAPITESTBNSPT0000003/download`, {
      headers: { Authorization: 'Bearer unb_live_testkey_dailystar_001' },
    });
    expect(res.status()).toBe(403);
    const json = await res.json();
    expect(json.error).toContain('not entitled');
  });

  test('GET /api/v1/media/{publicId}/download enforces media:read scope and generates presigned URL', async ({ request }) => {
    // Key with feed:read only (no media:read)
    const forbiddenRes = await request.get(`${base}/api/v1/media/01JWIREAPITESTMEDIA0000001/download`, {
      headers: { Authorization: 'Bearer unb_live_testkey_feedonly_002' },
    });
    expect(forbiddenRes.status()).toBe(403);
    const forbiddenJson = await forbiddenRes.json();
    expect(forbiddenJson.message).toBe('Insufficient scope');

    const initialCount = parseInt(execSync('php tests/e2e/helpers/seed-data.php download_count media').toString().trim(), 10);

    // Key with media:read
    const allowedRes = await request.get(`${base}/api/v1/media/01JWIREAPITESTMEDIA0000001/download`, {
      headers: { Authorization: 'Bearer unb_live_testkey_dailystar_001' },
    });
    expect(allowedRes.status()).toBe(200);
    const allowedJson = await allowedRes.json();
    expect(allowedJson).toHaveProperty('url');
    expect(allowedJson.expires_in).toBe(300);

    // Verify media download was ledgered in DB
    const newCount = parseInt(execSync('php tests/e2e/helpers/seed-data.php download_count media').toString().trim(), 10);
    expect(newCount).toBeGreaterThan(initialCount);
  });

  test('POST /api/v1/media/export validates media scopes and processes bulk export', async ({ request }) => {
    // Scope gate
    const forbiddenRes = await request.post(`${base}/api/v1/media/export`, {
      headers: { Authorization: 'Bearer unb_live_testkey_feedonly_002' },
      data: { asset_ids: ['01JWIREAPITESTMEDIA0000001'] },
    });
    expect(forbiddenRes.status()).toBe(403);

    // Authorized export
    const exportRes = await request.post(`${base}/api/v1/media/export`, {
      headers: { Authorization: 'Bearer unb_live_testkey_dailystar_001' },
      data: { asset_ids: ['01JWIREAPITESTMEDIA0000001'], variant: 'original' },
    });
    expect(exportRes.status()).toBe(200);
    const exportJson = await exportRes.json();
    expect(exportJson).toHaveProperty('download_url');
    expect(exportJson).toHaveProperty('filename');
    expect(exportJson.asset_count).toBe(1);
  });
});

test.describe('Client API Rate Limiting & Account Suspension Gates (M13-API)', () => {
  const base = process.env.LARAVEL_URL ?? 'http://localhost:8000';

  test.beforeAll(() => {
    execSync('php tests/e2e/helpers/seed-data.php wire_api', { stdio: 'pipe' });
  });

  test('Per-key rate limiting returns 429 Too Many Requests with Retry-After header', async ({ request }) => {
    const key = 'unb_live_testkey_ratelimited_003'; // configured with 2 RPM

    // 1st request -> 200
    const res1 = await request.get(`${base}/api/v1/feed`, {
      headers: { Authorization: `Bearer ${key}` },
    });
    expect(res1.status()).toBe(200);

    // 2nd request -> 200
    const res2 = await request.get(`${base}/api/v1/feed`, {
      headers: { Authorization: `Bearer ${key}` },
    });
    expect(res2.status()).toBe(200);

    // 3rd request -> 429
    const res3 = await request.get(`${base}/api/v1/feed`, {
      headers: { Authorization: `Bearer ${key}` },
    });
    expect(res3.status()).toBe(429);
    const res3Json = await res3.json();
    expect(res3Json.message).toBe('Rate limit exceeded');
    expect(res3.headers()['retry-after']).toBeDefined();
  });

  test('Suspended client keys are blocked immediately from feed and media endpoints', async ({ request }) => {
    const suspendedKey = 'unb_live_testkey_suspended_004';

    // Feed endpoint -> 401 (invalid/inactive key)
    const feedRes = await request.get(`${base}/api/v1/feed`, {
      headers: { Authorization: `Bearer ${suspendedKey}` },
    });
    expect(feedRes.status()).toBe(401);

    // Media endpoint -> 403 (Client suspended)
    const mediaRes = await request.get(`${base}/api/v1/media/01JWIREAPITESTMEDIA0000001/download`, {
      headers: { Authorization: `Bearer ${suspendedKey}` },
    });
    expect(mediaRes.status()).toBe(403);
    const mediaJson = await mediaRes.json();
    expect(mediaJson.message).toBe('Client suspended');
  });
});


