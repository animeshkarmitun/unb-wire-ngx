# Task: M13-RATE-001 — Environment-Aware Rate Limiting

**Status:** ⏳ Pending
**Dependencies:** None (all prior milestones complete)
**Parent ADR:** N/A — infrastructure improvement

---

## Problem Statement

All rate limits are hardcoded inline (`throttle:N,M` in routes, `5` attempts in `LoginRequest`, `60` RPM default in `EnsureClientApiKey`). Development/testing hits production limits, forcing manual workarounds. No central config, no env-based relaxation, no kill switch.

---

## 1. Contract (What)

### Inputs
- `.env` vars: `RATE_LIMIT_ENABLED`, `RATE_LIMIT_DEV_MULTIPLIER`
- Existing per-key `rate_limit_rpm` from `client_api_keys` table

### Outputs
- `config/rate-limiting.php` — single source of truth for all limits
- `RateLimitHelper` utility — resolves attempts/decay per environment
- Named `RateLimiter::for()` definitions in `AppServiceProvider`
- Updated routes + middleware to use config-driven limits

### Behavior

| Environment | `RATE_LIMIT_ENABLED` | `RATE_LIMIT_DEV_MULTIPLIER` | Effect |
|-------------|---------------------|----------------------------|--------|
| Production | `true` (default) | `1` (default) | Strict limits as defined |
| Development | `true` | `10` | 10x relaxed (60→600/min) |
| Testing | `false` | — | All limits disabled |

### Authorization
- No auth changes — existing RBAC/guest/auth middleware untouched

---

## 2. Logic (How)

### Step 1: Create `config/rate-limiting.php`
Define all endpoint limits in one file with `env()` calls. Sections:
- `enabled` — global kill switch
- `dev_multiplier` — multiplier for non-production
- `limits` — keyed array of `[attempts, decay]` per endpoint group

### Step 2: Create `App\Support\RateLimitHelper`
Static helper:
- `attempts(string $name): int` — reads config, applies multiplier if non-production
- `decay(string $name): int` — reads config
- `disabled(): bool` — returns `!config('rate-limiting.enabled')`

### Step 3: Register named rate limiters in `AppServiceProvider`
```php
RateLimiter::for('portal-login', ...);
RateLimiter::for('portal-feed', ...);
RateLimiter::for('client-api', ...);
// etc.
```
Each uses `RateLimitHelper::attempts()` and respects `disabled()`.

### Step 4: Update `routes/api.php`
Replace inline `throttle:N,M` with named limiters:
```php
// Before: ->middleware('throttle:60,1')
// After:  ->middleware('throttle:portal-feed')
```

### Step 5: Update `routes/auth.php`
Replace `throttle:6,1` on email verification with named limiter.

### Step 6: Update `EnsureClientApiKey` middleware
Apply `dev_multiplier` to per-key RPM:
```php
$rpm = $key->rate_limit_rpm ?: 60;
if (!app()->isProduction()) {
    $rpm = (int) ($rpm * config('rate-limiting.dev_multiplier', 10));
}
```
When `RATE_LIMIT_ENABLED=false`, skip `RateLimiter::hit()`/`tooManyAttempts()` entirely.

### Step 7: Update `LoginRequest`
Use `RateLimitHelper::attempts('staff_login')` instead of hardcoded `5`.

### Step 8: Add `.env.example` entries
```env
RATE_LIMIT_ENABLED=true
RATE_LIMIT_DEV_MULTIPLIER=10
```

### Step 9: Update tests
- Existing rate limit tests pass with production config
- New test: dev multiplier relaxes limits
- New test: `RATE_LIMIT_ENABLED=false` disables limits

### Step 10: Knowledge-inventory sync
Update `docs/knowledge-inventory/architecture.md` with rate limiting strategy.

---

## 3. Context (Where)

### Files to Create
- `config/rate-limiting.php`
- `app/Support/RateLimitHelper.php`
- `tests/Feature/RateLimitConfigTest.php`

### Files to Modify
- `app/Providers/AppServiceProvider.php` — register named limiters
- `routes/api.php` — replace inline throttle with named limiters
- `routes/auth.php` — replace inline throttle with named limiter
- `app/Http/Middleware/EnsureClientApiKey.php` — env-aware RPM
- `app/Http/Requests/Auth/LoginRequest.php` — use helper
- `.env.example` — add new env vars
- `docs/knowledge-inventory/architecture.md` — rate limiting section

### Reference Files
- `app-data/v1-non-functional-requirements.md` (§8 — rate limiting)
- `app-data/v1-functional-requirements.md` (FR-CLT-003, FR-PRT-006)
- `AGENTS.md` (§8 Security — rate limiting mention)

---

## 4. Prompt (For the Coding AI)

> Implement environment-aware rate limiting for UNB Wire.
>
> **Context:** All rate limits are currently hardcoded inline. Create a centralized config with dev-multiplier support.
>
> 1. Create `config/rate-limiting.php` with `enabled` (bool), `dev_multiplier` (int, default 1), and `limits` array keyed by endpoint name. Each limit has `attempts` and `decay`. Production values: portal_login=10/1, forgot_pw=3/1, reset_pw=5/1, portal_feed=60/1, portal_story=120/1, search_token=60/1, portal_session=60/1, client_feed=60/1, media_download=60/1, staff_upload=60/1, ai_assist=30/1, staff_login=5/1, email_verify=6/1.
>
> 2. Create `app/Support/RateLimitHelper.php` with static methods: `attempts(string $name): int` (applies dev_multiplier when `!app()->isProduction()`), `decay(string $name): int`, `disabled(): bool` (checks `config('rate-limiting.enabled')`).
>
> 3. In `AppServiceProvider::boot()`, register named rate limiters using `RateLimiter::for()` that call `RateLimitHelper` and return `Limit::none()` when disabled.
>
> 4. Update `routes/api.php`: replace all `throttle:N,M` with named limiter strings matching config keys. For routes with both `client.api` and throttle, use the named limiter only (per-key RPM handled in middleware).
>
> 5. Update `routes/auth.php`: replace `throttle:6,1` with named `email-verify` limiter.
>
> 6. Update `EnsureClientApiKey`: when `config('rate-limiting.enabled')` is false, skip rate limiting entirely. When non-production, multiply `$key->rate_limit_rpm` by `dev_multiplier`.
>
> 7. Update `LoginRequest::ensureIsNotRateLimited()`: use `RateLimitHelper::attempts('staff_login')` instead of hardcoded `5`.
>
> 8. Add `RATE_LIMIT_ENABLED` and `RATE_LIMIT_DEV_MULTIPLIER` to `.env.example`.
>
> 9. Create `tests/Feature/RateLimitConfigTest.php` with: (a) test that production limits match config values, (b) test that dev_multiplier relaxes limits, (c) test that enabled=false returns no 429s.
>
> 10. Update `docs/knowledge-inventory/architecture.md` §Security section with rate limiting strategy.
>
> Follow existing code style. No comments unless necessary. Run `php artisan test` and `php -l` on all changed files.

---

## 5. Test Criteria

- [ ] `php artisan test --filter=RateLimitConfigTest` passes
- [ ] `php artisan test --filter=ClientApiKeyTest` still passes (per-key rate limit)
- [ ] `php artisan test --filter=PortalApiTest` still passes (portal rate limits)
- [ ] `php artisan test` full suite green
- [ ] `php -l` clean on all modified PHP files
- [ ] With `RATE_LIMIT_ENABLED=false`, no endpoint returns 429
- [ ] With `RATE_LIMIT_DEV_MULTIPLIER=10` in dev, portal_feed allows 600/min
- [ ] Production values unchanged when env vars are unset

---

## 6. Completion Notes

- **Shipped:**
- **Tests:**
- **Live Smoke:**
- **Review:**

---

## 7. Prompt Ready?

- [x] Yes
