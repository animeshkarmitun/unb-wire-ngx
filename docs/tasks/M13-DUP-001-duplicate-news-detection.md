# Task: M13-DUP-001 — Duplicate news detection (title + body + pre-publish check)

**Status:** ✅ Completed
**Dependencies:** M13-AI-002 (AddNews test harness)
**Parent ADR:** DEC-014 (`stories.body_fingerprint`), FR-NWS-008 (publish flow), COS-20 issue spec

---

## 1. Contract (What)
- **Detect:** same title (normalized exact + Levenshtein fuzzy ≥ 0.85), same body (fingerprint exact + trigram Jaccard ≥ 0.75), similar content (combined weighted score 0.4·title + 0.6·body ≥ 0.45 warn / ≥ 0.72 high).
- **When:** as-you-type via debounced autosave (non-blocking warning banner in AddNews — "Similar story detected. Continue or revise?"); on publish attempt (blocking when a high match exists; override requires a 5–500 char reason, audited as `publish.duplicate_override`).
- **Candidates:** published stories from the last 30 days, same language (performance rule from the issue).
- **Storage:** `stories.body_fingerprint` (sha1 of normalized body, indexed) computed centrally in `StoryService::createDraft`/`updateDraft` — DEC-014 (schema-parity gate §6).

---

## 2. Logic (How)
1. `DuplicateDetectionService::normalize` (lowercase, strip tags/punctuation, collapse whitespace) + `::fingerprint` (sha1 of normalized).
2. `matches(title, bodyText, language?, excludeId?)` → candidates via indexed query (columns only) → per-candidate title score (1 − levenshtein/maxlen) + body score (trigram Jaccard; fingerprint equality ⇒ 1.0) → level high if exact title / exact fingerprint / title ≥ 0.85 / body ≥ 0.75; warn if combined ≥ 0.45. Sorted by combined desc. Meilisearch is the documented scale path — v1 matching is local so it runs without the search stack.
3. `AddNews::autosave` → sets `dupMatches` (non-blocking). `AddNews::publish` → re-checks; high matches with an empty/short override reason set `dupBlocked` + toast and stop the publish; a valid reason writes the audit record and proceeds. `quickPublish` shares `publish`.
4. UI: warn banner (step 1) + block/override panel with match links and scores (step 4).

---

## 3. Context (Where)
- **Files Created/Modified:**
  - `database/migrations/2026_09_23_000100_add_body_fingerprint_to_stories.php` (new — DEC-014)
  - `app/Services/DuplicateDetectionService.php` (new)
  - `app/Services/StoryService.php` (fingerprint write path)
  - `app/Livewire/Admin/AddNews.php` (warning + block + override audit)
  - `app/Models/Story.php` (`body_fingerprint` fillable)
  - `resources/views/livewire/admin/partials/add-news-step1-write.blade.php`, `add-news-step4-review.blade.php` (banners)
  - `tests/Feature/DuplicateNewsTest.php` (new, 11 tests)
  - `docs/knowledge-inventory/decisions.md` (DEC-014), `docs/knowledge-inventory/data-model.md`

---

## 4. Test Criteria
- [x] Exact/fuzzy title + body similarity + unrelated negative + window/language scoping (6 service tests)
- [x] Fingerprint written on create + update
- [x] Autosave warning non-blocking
- [x] Publish blocked without override; override publishes + audits; short reason stays blocked
- [x] Full `php artisan test` green · schema parity PASSED · `migrate:fresh --seed` clean

---

## 5. Completion Notes
- **Shipped:** As specified in §1/§2. Override reason validated inline (5–500 chars) instead of a FormRequest rule so the block state renders with the toast (Livewire validate() throws before state persists).
- **Tests:** `DuplicateNewsTest` 11 passed (25 assertions) — full suite **750 passed, 1 skipped, 0 failures**; `php -l` clean; parity PASSED (DEC-014 patch included); `migrate:fresh --seed` clean.
- **Live Smoke:** `optimize:clear`; `monitor:outbox-lag` OK lag=0s; `/api/v1/portal/feed` 200; `/admin` guest 302→/login. Server stopped after smoke.
- **Review:** PR.
