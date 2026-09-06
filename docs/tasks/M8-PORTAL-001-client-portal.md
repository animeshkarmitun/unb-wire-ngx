# Task: M8-PORTAL-001 — Client Portal Faithful Conversion (Next.js)

**Status:** ✅ Completed
**Dependencies:** M8-DELIV-001, M4-SEARCH-001, M5-PORTAL-001
**Parent ADR:** app-data/client-portal.html, DEC-006, DEC-007

---

## 1. Contract (What)
- **Inputs / Validation:**
  - `GET /api/v1/portal/feed`: optional `category`, `language`, `is_breaking`, `limit`.
  - `GET /api/v1/portal/context`: returns client organization details, subscription tier (`Premium`), renewal date, stories quota, media downloads quota.
  - `POST /api/v1/portal/search-token`: returns scoped Meilisearch tenant token.
- **Outputs / Response:**
  - Next.js portal application (`portal/`) rendering 1:1 with `app-data/client-portal.html`.
  - Masthead: Crimson `U` logo, UNB Wire, Client Portal label, Asia/Dhaka live clock, Newsroom link, subscriber user chip & dropdown.
  - Collapsible left rail: Subscription card, quota progress bars, language/category/media filters, saved searches, recent downloads.
  - Omnisearch: `/` keyboard shortcut, scope selector, search count, browser-direct Meilisearch query with fallback.
  - 3 Interchangeable Views:
    1. List View (`.story`): story cards with headline, brief, category, tags, media indicator, full dispatch expander (`.st-body`), copy wire dispatch, download pack, share.
    2. Terminal Wire Table (`table.tw`): density switcher (compact/comfortable), column sorting, category dots, locked indicators, inline detail drawer.
    3. Visual Grid View (`.wire-grid`): 3-column media cards with gradient thumbnails (g1–g8), durations, headline clamps, expand action.
  - Media Library & Curated Packages tabs: sources (UNB/AP), embargo overlays, entitlement badges.
  - Lightbox modal (`.lb`): photo/video stage, caption, count, download, prev/next keyboard/click navigation.
  - Toast feedback alerts for copy, download, and filters.
- **Authorization:**
  - Public/Subscriber wire feed, scoped Meilisearch tenant token based on client packages/entitlements.

---

## 2. Logic (How)
1. Port all CSS design tokens, typography (Fraunces, Inter, Source Serif 4), animations, quota bar styles, gradients `g1`–`g8`, and terminal table styles to `portal/app/globals.css`.
2. Implement modular React components in `portal/app/components/` and `portal/app/page.tsx` for Masthead, LeftRail, Omnisearch, ViewSwitcher, StoryCard, WireTable, WireGrid, MediaLibrary, MediaPackages, Lightbox, and Toast.
3. Wire live Asia/Dhaka clock interval updating every 30 seconds.
4. Support browser-direct Meilisearch queries via `searchStories` with graceful fallback to `GET /api/v1/portal/feed` and rich prototype datasets.
5. Implement raw wire copy formatting (Headline, Dateline, Body, Signoff) with clipboard API and toast feedback.
6. Support inline `.st-body` expand and terminal table row expand.
7. Verify with `portal` build (`npm run build`) and Playwright E2E test suite (`portal-faithful.spec.ts`, `portal-ui.spec.ts`, `portal-search.spec.ts`, `portal-story-pagination-search.spec.ts`).

---

## 3. Context (Where)
- **Target Files:**
  - `portal/app/globals.css`
  - `portal/app/layout.tsx`
  - `portal/app/page.tsx`
  - `portal/app/types.ts`
  - `portal/lib/format.ts`
  - `portal/lib/mockData.ts`
  - `portal/next.config.ts`
  - `routes/api.php`
  - `tests/e2e/portal-faithful.spec.ts`
- **Reference Files:**
  - `app-data/client-portal.html`

---

## 4. Test Criteria
- [x] `cd portal && npm run build` compiles without TypeScript or ESLint errors (Turbopack).
- [x] Masthead renders with live Asia/Dhaka clock and subscriber profile.
- [x] View switcher toggles between List, Terminal Table, and Visual Grid views.
- [x] Collapsible left rail toggles smoothly with filters and saved searches.
- [x] Filter chips and language select filter stories.
- [x] Omnisearch triggers via input and `/` key.
- [x] Read full dispatch expands inline; Copy dispatch copies wire text to clipboard.
- [x] Lightbox opens with photo/video preview and next/prev controls.
- [x] Bulk bar activates when stories are selected with Word, XML, CSV export.
- [x] Media Library tab switches panels and opens Asset Detail Modal.
- [x] UNB Photos showcase renders Photo of the Day hero, photo stories, and collections.
- [x] All 21 Playwright tests pass in `tests/e2e/`.
- [x] All 204 PHPUnit/Pest tests pass (`php artisan test`).
- [x] Schema parity check 12/12 PASS (`php scripts/schema-parity-check.php`).

---

## 5. Completion Notes
- Completed faithful 1:1 conversion of `app-data/client-portal.html` in Next.js (`portal/`).
- Implemented 3 interchangeable wire views: Cards with inline dispatch expansion, 3-column Visual Grid with zoom thumbs and reading mode, and Terminal Wire Table with compact/comfortable density switcher and column sorting.
- Implemented Media Library tab with live faceted filters (source, category, access level, date, sort, compact/cozy density), download basket ZIP generation with JSZip, and Asset Detail Modal (`#amOverlay`).
- Implemented UNB Photos showcase tab featuring Photo of the Day hero, 3 Photo Stories with gallery browse/ZIP/embed, 4 Collections with email notification bell toggle, Trending list, and latest photographer stream.
- Implemented fullscreen Lightbox modal (`#lightbox`) with prev/next and keyboard arrow navigation.
- Upgraded Laravel API (`GET /api/v1/portal/feed`, `GET /api/v1/portal/context`) to provide subscriber metadata, quotas, and tag eager-loading.
- Passed all Playwright E2E tests (`portal-faithful.spec.ts`, `portal-ui.spec.ts`, `portal-search.spec.ts`, `portal-story-pagination-search.spec.ts`) and backend tests (`php artisan test`).
