# Task: M13-GA-001 — Google Analytics 4 tracking (public pages)

**Status:** ✅ Completed
**Dependencies:** M8-PORTAL-001
**Parent ADR:** COS-22 spec (GA4 gtag on public pages, `GA_MEASUREMENT_ID` env, page + story views, admin excluded)

---

## 1. Contract (What)
- **Scope (per issue):** GA4 `gtag.js` on the public consumer surfaces — wire feed (`portal/app/page.tsx`) + story reader (`portal/app/story/[id]/page.tsx`) — both live in the **Next.js client portal** (the Laravel "wire service"/"story reader" routes are admin-gated `rbac` surfaces; the admin panel is excluded per the issue). Optional items (GDPR banner, server-side portal API tracking) skipped per "Optional".
- **Config:** `GA_MEASUREMENT_ID` env (issue name) → mapped to `NEXT_PUBLIC_GA_MEASUREMENT_ID` via `portal/next.config.ts` for browser exposure. Empty/unset ⇒ **renders nothing** (privacy-safe default).
- **Events:** `page_view` (on every portal route change) + `story_view` (`story_id` param on `/story/*`).

---

## 2. Logic (How)
1. `portal/lib/ga.ts` — `GA_ID` constant + `gaEvent()` no-op helper.
2. `portal/components/Analytics.tsx` (client) — renders gtag bootstrap + loader scripts only when `GA_ID` set (ID sanitized before inline interpolation); `usePathname` effect sends `page_view`, and `story_view` when the path matches `/story/<id>` (the story page itself is a server component — path-based tracking avoids touching it).
3. `portal/app/layout.tsx` — `<Analytics />` in the root body.
4. `portal/next.config.ts` — `env.NEXT_PUBLIC_GA_MEASUREMENT_ID = process.env.GA_MEASUREMENT_ID ?? ""`.
5. `.env.example` — `GA_MEASUREMENT_ID=` documented.

---

## 3. Context (Where)
- **Files Created/Modified:** `portal/lib/ga.ts` (new), `portal/components/Analytics.tsx` (new), `portal/app/layout.tsx`, `portal/next.config.ts`, `.env.example`, `tests/e2e/portal-analytics.spec.ts` (new)

---

## 4. Test Criteria
- [x] Portal renders no GA script when unconfigured (privacy default) — e2e
- [x] Story reader renders no GA script when unconfigured — e2e
- [x] Admin login carries no GA script (admin excluded) — e2e
- [x] Configured build inlines the measurement ID + gtag loader (positive path)
- [x] `portal npm run build` clean · full `php artisan test` green

---

## 5. Completion Notes
- **Shipped:** Per §1/§2. Admin exclusion is structural (Analytics lives only in the portal layout).
- **Tests:** `tests/e2e/portal-analytics.spec.ts` 3 passed locally (`npx playwright test tests/e2e/portal-analytics.spec.ts`) — absence assertions run in CI (no env set there).
- **Positive-path smoke:** `GA_MEASUREMENT_ID=G-TEST123456 npm run build` → measurement ID present in prerendered `server/app/index.html` + `account.html` and `googletagmanager` loader present in the client chunk — i.e. with the env configured the tag ships on the public pages.
- **Live Smoke:** portal production build clean (routes `/`, `/account`, `/story/[id]`); PHP suite **752 passed, 1 skipped** (untouched).
- **Review:** PR.
