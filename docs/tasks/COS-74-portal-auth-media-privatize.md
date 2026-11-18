# Task: COS-74 — Portal API & Media Require Authentication (P1 Security)

**Status:** 🔧 In Progress
**Priority:** P1 (Security — paid wire content publicly downloadable)
**Linear:** [COS-74](https://linear.app/cosmosgroupcom/issue/COS-74/security-wire-stories-full-articles-and-photos-can-be-downloaded)
**Affected surfaces:** `/api/v1/portal/*` (feed, story, context, search-token), media storage/serving
**Parent contracts:** `app-data/v1-functional-requirements.md` §9 ("Client users log in with their portal account; everything they see is scoped by entitlements"), `app-data/v1-non-functional-requirements.md` §17 ("UNB is B2B wire; portal serves clients only")

---

## 1. Issue Summary

All three portal read endpoints respond 200 with **no authentication**, and every uploaded
photo (incl. licensed AP photos) is downloadable from the public `/storage/...` path:

1. `GET /api/v1/portal/feed` → full wire, no token (`resolve.client:optional` guest mode).
2. `GET /api/v1/portal/story/{public_id}` → full `body_html`, no token.
3. `GET /api/v1/portal/context` → 200 with `client: null`.
4. `POST /api/v1/portal/search-token` → guest tenant token (search across all published
   content without auth — same hole via the Meilisearch edge).
5. `GET /storage/media/...` → 200 (nginx static via `public/storage` junction; media
   originals + derivatives live on the `public` disk).

Tier limits, quotas and the AP licence are meaningless while this is open.

## 2. Root Causes

1. `routes/api.php:19` — portal read group uses `resolve.client:optional`; `ResolveClient`
   mode `optional` proceeds with `client = null` (guest) — guests see everything because
   entitlement filters are applied only when `$client` is non-null.
2. `IntakeService` stores originals on the `public` disk (`storage/app/public`, web-served
   via the `public/storage` junction) and derivatives follow the same disk.
3. `PresignedUrlService::forAsset` / `MediaZipExportService` fall back to decorative
   `url("/storage/...?expires=...")` links for local disks (no signature, no auth).

## 3. Fix Design

### A. Portal API requires auth (commit 1)
- `resolve.client:optional` → `resolve.client` (default required) on the
  `v1/portal` read group → feed/story/context/search-token return
  `401 {"message":"Unauthenticated"}` without a valid Sanctum portal token or client API key.
- `PortalController`: remove guest branches (`'guest'` cache-key fallback, `client: null`
  context response, skip-entitlement branches). Cache keys become per-client only.
- `Cache-Control: public, max-age=60` → `private, max-age=60` (per-client responses must
  not be shared-cacheable).
- `TenantTokenIssuer::issueFor(null)` guest branch left in place (service-level defensive
  code, now unreachable via routes).

### B. Media privatization (commit 2)
- `IntakeService` writes originals to the private `local` disk (`storage/app/private`,
  never web-served); `storage_disk = 'local'`. Derivatives follow `storage_disk`.
- New signed client file route `GET /api/v1/media/{asset}/file?variant=` (named
  `media.file`, `signed` middleware, throttle `media-download`):
  - re-checks embargo (`MediaAsset::clientVisible`),
  - streams local-disk files with correct `Content-Type` / inline disposition,
  - redirects to `temporaryUrl` for S3-backed assets.
- `PresignedUrlService::forAsset` fallback for `public`/`local` disks →
  `URL::temporarySignedRoute('media.file', ...)` (5-min TTL) instead of the decorative
  public `/storage` link. S3 unchanged.
- `MediaZipExportService` fallback → same signed route.
- `MediaAsset::thumbUrl()` becomes disk-aware: `local` → staff-authed admin route
  `GET /admin/media/{asset}/file?variant=thumb` (`auth` + `rbac:media,view`);
  `public` (legacy pre-migration) → `Storage::url`; `s3` → direct URL.
- `add-news-modals.blade.php` uses `$m->thumbUrl()` (was raw `Storage::url()`, which
  breaks for private-disk paths after migration).
- New command `php artisan media:privatize`: moves every `storage_disk='public'`
  asset (original + derivatives) to the `local` disk, updates rows, then sweeps orphan
  files under `media/**` on the public disk (move, not delete — no data loss).
  **Must be run on the live server during deploy** (see §5).

## 4. Verification Plan

- [x] New/updated feature tests: portal 401 enforcement (feed/story/context/search-token),
      revoked/expired/invalid keys → 401, rate-limit tests authenticated, `MediaFileTest`
      (signed 200 / unsigned 403 / embargo 404 / s3 redirect), `media:privatize` command test.
- [x] `php -l` on all changed PHP files; `php artisan test` — **993 passed, 6 skipped, 0 failed**;
      `php scripts/schema-parity-check.php` — all PASSED; `php artisan migrate:fresh --seed` — clean.
- [x] Local smoke (`php artisan serve` :8011): unauth feed/context/search-token = **401**;
      client API key → feed 200 (entitlement-filtered), context 200 (client data);
      presigned download → signed `/api/v1/media/{id}/file` URL → **200** image/jpeg;
      unsigned file route → **403**; story download → 200.
- [x] `php artisan media:privatize` executed locally: moved 222 orphan files off the public
      disk; `/storage/media/...` → **404** afterwards; signed media route still serves bytes.
- [x] **Live server verified (2026-11-18, 147.93.171.193):**
  - unauth feed/context → **401**; client login (The Daily Star) → feed 200 (entitlement-filtered), story detail 200
  - presigned download → signed `media.file` URL → **200** image/jpeg; unsigned file route → **403**; presign without token → **401**
  - `media:privatize` executed on server: 45 assets moved; `/storage/media/ap/...` → **404**
  - admin panel & login page unaffected (200)
  - **Deploy gotcha:** `docker exec` runs as root — moved files/dirs need `chmod u+rwX,go+rX` or php-fpm (www-data) cannot traverse them (symptom: signed URL → 404). `media:privatize` now normalizes perms automatically (0644 files / 0755 dirs below the disk root).

## 5. Known Follow-ups (out of scope here)

- **E2E suite (~15 specs)** hits `/api/v1/portal/*` unauthenticated and will need
  login-token injection (nightly/on-demand suite, not a push gate — tracks under
  existing `M14-COV-028` red-suite TODO; noted on the Linear issue).
- `EntitlementResolver::forClient` fail-open default (`['en','bn']`) means a client with
  **no active package** still sees all en/bn content — pre-existing gap, separate fix.
- `MediaZipExportService` zip fallback still uses the decorative S3-CDN link (zip flow
  requires S3 anyway); left untouched.
- `scripts/linear-client.cjs` gained no update/update-state support (list/get only).

## 6. Completion Notes

- **Branch:** `fix/cos74-portal-auth-media-privatize` — two commits: `M17-PUB-001`
  (pre-existing uncommitted backdate work split out first) then the COS-74 fix.
- Note: `Storage::fake()` overrides `temporaryUrl()` with a signature-less callback, so
  `PresignedUrlService::forAsset` now routes local/public disks **deterministically**
  through the signed `media.file` route instead of relying on the temporaryUrl path.
- `config/filesystems.php`: `serve => false` on both `local` and `public` disks removes
  Laravel's own unauthenticated `/storage/{path}` GET (and PUT) serve routes.
