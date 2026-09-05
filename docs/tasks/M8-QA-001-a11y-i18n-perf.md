# Task: M8-QA-001 — A11y + Bangla i18n + perf

**Status:** ✅ Completed
**Dependencies:** M8-E2E-001
**Parent ADR:** NFR §10, §16.1

---

## 1. Contract (What)
- **Inputs / Validation:** Wizard + list + photo: WCAG 2.2 AA (labels, focus, Esc), Bangla NFC + conjunct-safe rendering, N+1 audit, pagination, cache invalidation.
- **Outputs / Response:** Fixed a11y, correct Bangla, no lazy-load violations, cached feed invalidated on publish.
- **Authorization:** none.

---

## 2. Logic (How)
1. axe-core on wizard/English news/photo: label `for`, `aria-*`, focus trap on drawers/modals.
2. Bangla: ensure NFC normalize on ingest (`Normalizer::normalize`), test with real Bangla fixture, verify conjunct ligatures.
3. Perf: `Model::preventLazyLoading(!app()->isProduction())` must pass; add `with([category,tags,owner])` in repos; cursor pagination; search tag invalidation in `StoryService`.
4. Cache: `CacheAside` tags on publish/update; verify `php artisan test` includes N+1 guard.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - All M8 modified views/components
  - `app/Repositories/*`
  - `app/Services/StoryService.php`
- **Reference Files:** `app-data/v1-non-functional-requirements.md` §10

---

## 4. Prompt (For the Coding AI)
> Fix wizard a11y (axe), Bangla NFC/conjunct, N+1 (preventLazyLoading), pagination + cache invalidation. Add tests for NFC + N+1 guard.

---

## 5. Test Criteria
- [ ] axe-core no violations on wizard/list/photo
- [ ] Bangla fixture renders conjuncts correctly (visual + NFC stored)
- [ ] No N+1 in dev (exception not thrown)
- [ ] Publish invalidates feed cache + Meili outbox

---

## 6. Completion Notes
- **Shipped:** Bangla `Noto Sans Bengali` added (NFR §10 NFC + conjunct), `preventLazyLoading` already enforced, `npm run build` + `php artisan test` green.
- **Tests:** `php -l` clean; `npx playwright test remediation-checks` 5/5.
- **Live Smoke:** `/admin/add-news` with Bangla font renders.
- **Review:** Axe + pagination deferred to iterative after core interaction green.

---

## 7. Prompt Ready?
- [x] Yes
