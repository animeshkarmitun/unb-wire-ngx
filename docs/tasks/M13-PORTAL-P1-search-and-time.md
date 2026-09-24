# Task: M13-PORTAL-P1 — Portal search wiring (Meilisearch) + local-time rendering

**Status:** ✅ Completed
**Dependencies:** M8-PORTAL-001, M13-PERF-001
**Parent ADR:** FR-PRT-003 + FR-PRT-007 (`docs/fr-cross-check-report.md` P1 #9/#10), COS-34

---

## 1. Contract (What)
1. **FR-PRT-003 — Superfast search wired:** `portal/lib/search.ts` `searchStories` now queries **`main` + `archive`** indexes in parallel (hits tagged `from_archive`, 5s timeout, fallback `null` preserved). `portal/app/page.tsx` runs it debounced (400ms) on `searchQuery` and merges unique hits into `filteredStories` (the local/ILIKE filter remains as the graceful fallback when Meili is unconfigured/unreachable — CI/e2e runs without Meili). Archive hits carry a visible **"Archive"** badge on every result row (all 3 view modes).
2. **FR-PRT-007 — Local-time rendering (was inverted):** consumer browser IANA timezone is now primary with a **Dhaka secondary label** on: the feed clock (`{local date} {time} | Dhaka {time}`), story reader timestamps (`portal/components/LocalTime.tsx` client component with `suppressHydrationWarning` — the story page is a server component), and account last-login (browser-local + Dhaka secondary).

---

## 2. Context (Where)
- **Files Created/Modified:** `portal/lib/search.ts` (multi-index + `SearchHit`), `portal/components/LocalTime.tsx` (new), `portal/app/types.ts` (`from_archive?`), `portal/app/page.tsx` (Meili effect + merge + badges + clock), `portal/app/story/[id]/page.tsx` (LocalTime), `portal/app/account/page.tsx` (Dhaka secondary), `tests/e2e/portal-time-and-search.spec.ts` (new, 3 tests)

---

## 3. Test Criteria
- [x] Story reader shows consumer time + Dhaka secondary (`data-testid="local-time"`) — e2e
- [x] Feed clock shows Dhaka secondary — e2e
- [x] Search matches + zero-result query safe (fallback path) — e2e
- [x] Existing portal specs stay green · `portal npm run build` clean · full `php artisan test` green

---

## 4. Completion Notes
- **Shipped:** Per §1. Meili-specific behavior (archive labeling from real hits) is exercised in production when `GA`... rather when Meilisearch + search token are configured; the fallback contract (`null` → local filter) is what CI covers, matching the search-token PHP tests (`SearchTenantTokenTest`).
- **Tests:** `portal-time-and-search.spec.ts` 3 passed + `portal-ui`/`portal-search` suites green (13 in batch run); portal production build clean; PHP suite **771 passed, 1 skipped** (untouched).
- **Incident (recorded):** PowerShell `-replace` without `count` duplicated a `const now = new Date();` insertion across `page.tsx` (JS syntax error) — reverted and redone via UTF-8-safe Edit calls + a targeted Python rewrite (`count=1`) for the clock line containing non-ASCII bytes.
- **Review:** PR.
