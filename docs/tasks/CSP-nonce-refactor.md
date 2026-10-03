# Task: CSP nonce refactor — full script-src hardening

**Status:** ✅ Completed
**Dependencies:** M13-SEC-001 (SecurityHeaders)
**Parent ADR:** `docs/security-audit-report.md` #8 warn ("full script-src CSP deferred — inline-script heavy; nonce-based CSP is a dedicated hardening task")

---

## 1. Contract (What)
`SecurityHeaders` shipped with a minimal CSP (`frame-ancestors`/`base-uri`/`object-src` only) because the admin surface is inline-script heavy. Now a **nonce-based full CSP**:
- `script-src 'self' 'nonce-{per-request}' https://cdn.jsdelivr.net` — inline `<script>` blocks (toast, topnav, add-news wizard init, delivery-settings, story-view) tagged `nonce="{{ $cspNonce }}"`; CDN scripts (Quill/mammoth/jszip) host-allowed; Vite-built bundles are `'self'`
- `style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net` (blade `style=` attributes + Quill/Fonts CSS)
- `img-src 'self' data: blob:` · `font-src 'self' data: https://fonts.gstatic.com` · `connect-src 'self' ws: wss:` (Reverb sockets)
- Inline `onclick=` handlers (2, Breeze logout links in `navigation.blade.php`) converted to `x-on:click.prevent` — inline event handlers are `script-src-attr`-blocked without `unsafe-inline`

---

## 2. Context (Where)
- **Files Modified:** `app/Http/Middleware/SecurityHeaders.php` (nonce + CSP), `resources/views/components/{toast,topnav}.blade.php`, `resources/views/livewire/admin/{add-news,delivery-settings,story-view}.blade.php` (nonce), `resources/views/layouts/navigation.blade.php` (onclick → x-on), `tests/Feature/SecurityHeadersTest.php` (CSP shape + per-request nonce uniqueness), `tests/e2e/portal-faithful.spec.ts` (pre-existing reds fixed — see §4)

---

## 3. Test Criteria
- [x] CSP carries per-request nonce + full directives (phpunit)
- [x] Nonce unique per request
- [x] **Full Playwright suite green** (chunked sweep across all 25 specs — wizard/editorial 5, photo/delivery/dashboard 7, ai/ap/notifications/audit 6, clients/packages/news/… 28, portal 48) — inline-script surfaces verified under the enforced CSP
- [x] Full `php artisan test` green

---

## 4. Completion Notes
- **Shipped:** Per §1. `View::share('cspNonce')` from the middleware keeps all blades able to render the nonce.
- **Pre-existing e2e reds fixed (surfaced by the full-suite sweep):** `portal-faithful.spec.ts` ×2 — they asserted prototype-mock context literals (`"342/500"`, `"renews 1 Oct 2026"`, `data-q="election"`, `"Daily Star"`) that the **dynamic `/context` endpoint** (M11-PORTAL-001) replaced; they also ran unauthenticated (`#userBtn` only renders for a logged-in client). Now: `loginAsClient('dailyStar')` in `beforeEach`, loose quota/name assertions against real seeded context.
- **Notes:** Livewire runtime scripts ship through the Vite bundle (`'self'`) — verified live in the e2e sweep. Dev-mode Vite dev-server origins are intentionally NOT allowlisted (e2e runs built assets per the runbook).
- **Review:** PR.
