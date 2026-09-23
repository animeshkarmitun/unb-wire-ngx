# Task: M13-SEC-001 — Security audit (12 areas) + critical fixes

**Status:** ✅ Completed
**Dependencies:** M13-QA-FIX-001 (P0 state as of 2026-09-23)
**Parent ADR:** NFR §8 security topology; `docs/security-audit-report.md` (deliverable)

---

## 1. Contract (What)
- **Inputs:** COS-27's 12 audit areas (authn, authz/IDOR, SQLi, XSS, CSRF, uploads, secrets, headers, rate limiting, dependencies, API keys, portal tenant isolation) vs codebase.
- **Outputs:** `docs/security-audit-report.md` checklist (pass/warn/fail + evidence) + **critical fixes in the same PR**:
  1. `SecurityHeaders` middleware (nosniff, DENY frame, referrer policy, permissions policy, CSP frame-ancestors/base-uri/object-src, HSTS on HTTPS only)
  2. API key show-once (hide consumes `rawApiKey`; no hash-derived display; demo raw constants removed) — FR-CLT-003
  3. `ApiKeyService` issue/rotate/revoke → `audit_logs` — FR-NTF-003 key-ops gap
  4. `AiService` `DB::raw` interpolation → int casts (SQLi hardening)
- **Verdicts:** 9 PASS · 3 FAIL→FIXED · warns documented (full CSP nonce refactor, TUS ingest completeness → FR-MED track, masked cosmetic demo strings).

---

## 2. Logic (How)
1. Dependency audits (`composer audit`, `npm audit`) + secrets git scan + middleware/route config review.
2. Code sweep: `{!! !!}` blades (4 — all sanitized paths), raw SQL (7 — 1 interpolated, fixed), JS `innerHTML` (esc()-covered; preview self-only), upload entry points (MIME/size/hashed store), LoginRequest throttle + named limiters, portal isolation test evidence.
3. Fixes per §1; `SecurityHeadersTest` (headers over HTTP, no HSTS on HTTP), `DeliverySettingsTest::test_api_key_reveal_is_one_time_only` + `test_api_key_lifecycle_is_audited` replace the old re-reveal test.

---

## 3. Context (Where)
- **Files Created/Modified:**
  - `docs/security-audit-report.md` (new)
  - `app/Http/Middleware/SecurityHeaders.php` (new) + `bootstrap/app.php` (web append)
  - `app/Livewire/Admin/DeliverySettings.php` (show-once)
  - `app/Services/ApiKeyService.php` (audit)
  - `app/Services/AiService.php` (casts)
  - `tests/Feature/SecurityHeadersTest.php` (new), `tests/Feature/DeliverySettingsTest.php`

---

## 4. Test Criteria
- [x] All 12 areas have verdict + evidence in the report
- [x] Headers present on web responses (live-smoked) + HSTS HTTPS-only
- [x] API key raw shown once (reveal→hide→no re-reveal); never derived from hash
- [x] API key issue/rotate/revoke audited
- [x] Full `php artisan test` green · `php -l` clean

---

## 5. Completion Notes
- **Shipped:** `docs/security-audit-report.md` + 4 fixes per §1. `test_api_key_reveal_and_two_step_rotation` rewritten as `test_api_key_reveal_is_one_time_only` (old test enshrined the re-reveal violation).
- **Tests:** 3 new (SecurityHeaders ×2, lifecycle audit ×1) + 1 rewritten — full suite **739 passed, 1 skipped, 0 failures**; `php -l` clean on 6 files.
- **Live Smoke:** `optimize:clear`; `monitor:outbox-lag` OK lag=0s; `/login` 200 with all 5 headers present, HSTS absent over HTTP (correct); `/api/v1/portal/feed` 200. Server stopped after smoke.
- **Review:** PR.
