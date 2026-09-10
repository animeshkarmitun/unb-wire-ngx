# Codebase Audit — Remaining To-Do

**Audit date:** 2026-09-06  
**Completed:** Phase 1 (C1–C3, C6 false positive), Phase 2 (W1–W3, C5, C7 partial)

---

## Open Questions (Need Decision)

> These need your input before the related items can be implemented.

- [ ] **Q1 — Missing API surface (C8):** Many FRD endpoints are absent (Story CRUD, workflow transitions, internal notes, media approval, role management, package management, distribution/webhooks). Are these intentionally deferred (handled by Livewire server-side actions for now), or genuinely missing and need REST endpoints built?
- [ ] **Q2 — HTML purification strategy:** `HtmlSanitizer::clean()` already runs at write-time. Should we add render-time purification as a defense-in-depth layer (`{!! clean($bodyHtml) !!}`), or is write-time sufficient?
- [ ] **Q3 — Test database:** Are you open to switching tests from SQLite in-memory to a dedicated PostgreSQL test database? SQLite can mask PG-specific behavior (JSONB, CHECK constraints, `timestamptz`). Tradeoff is slower tests.
- [ ] **Q4 — CSS architecture:** Custom CSS files (wire-service.css, news-desk.css, etc.) are extensive. Full Tailwind migration now, or future concern?

---

## Phase 2 — Architecture & Code Quality (COMPLETED)

- [x] **C7 — ClientsManager service extraction** (1095 lines → `ClientService`) — Commit `6ba8e7f`
  - Extracted onboarding wizard logic into service
  - Extracted channel management (toggle, FTP, API key, webhook) into service
  - Extracted package/subscription operations into service
  - Extracted bulk actions (pause, apply package) into service
  - Extracted stats computation into service
  - Component now strictly retains UI state + thin orchestration (1,094 → 654 lines)

---

## Phase 3 — Test Suite

- [x] **C4 — Rewrite E2E tests** — Current tests were fake success patterns (rewritten & verified)
  - `editorial-flow.spec.ts` — rewritten with real Playwright interactions: login → create draft → step through wizard → publish → verify DB & Portal API feed
  - RBAC test — verified actual access denied behavior for Business Team role across add-news & news-list
  - `web-auth-rbac.spec.ts` — rewritten with deterministic URL redirections and login authentication tests
- [x] **Add Feature tests for AddNews** — Commit `58909cd` (34 tests, 105 assertions covering stepper, tags, media, takeover, notes, syncFromDoc, AI flags, publish lifecycle)
- [x] **Add Feature tests for Portal API** — Commit `15cbad4` (24 tests, 333 assertions covering all 4 endpoints: feed, story/{id}, search-token, context, throttling)
- [x] **Add Feature tests for TusController** — chunked upload endpoints — Commit `TEST-002` (8 tests covering guest/role auth, session create, HEAD offset, PATCH append, offset mismatch 409, completion, ownership isolation)
- [x] **Add Job tests with `Queue::fake()`:**
  - `GenerateDerivatives` — media processing — Commit `TEST-002` (2 tests: derivatives population, skip-if-already-processed)
  - `ProcessIndexOutbox` — Meilisearch sync — Commit `TEST-002` (3 tests: done on no-config, failure after max attempts, retry on transient failure)
- [x] **W4 — Complete model factories** (currently 6 of 30 models)
  - Missing: `MediaAsset`, `Delivery`, `Tag`, `Role`, `MediaBatch`, `ClientChannel`, `ClientPackage`, `ClientApiKey`, `StoryVersion`, `StoryEvent`, `InternalNote`, `Setting`, and others
- [ ] **W9 — Consider PostgreSQL test database** (blocked by Q3)
- [x] **Add tests for untested services:**
  - `PresignedUrlService` — media URL generation — Commit `TEST-002` (2 tests: download ledger + count increment, skip when no client)
  - `NoteService` — internal notes — Commit `TEST-002` (1 test: creates internal note with event + audit)
  - `NotificationService` — Commit `TEST-002` (2 tests: dispatches to Editor/Admin roles, burst dedup within window)

---

## Phase 4 — Polish & Hardening

- [x] **W6 — Add read replica config** — PostgreSQL `read`/`write` array separation in `config/database.php`
- [x] **W8 — Form Request authorization** — `AiAssistRequest` and `TusCreateRequest` return `authorize() → true` unconditionally; add ownership/permission checks
- [ ] **W10 — Branch protection** — Add `.github/rulesets/` config (CI checks exist in `ci.yml` but protection relies on manual GitHub settings)
- [x] **W11 — Accessibility** — Add `alt` text and `aria-label` to image placeholders, icons, custom inputs across admin views — Commit `A11Y-001` (topnav: notifications bell, user menu, portal link; stepper: `aria-current="step"` + step labels; news-list: action buttons with story headline; photo-manager: tab/bulk/upload buttons; ap-photo-manager: sync/expand/download/attach buttons; roles-manager: modal close, edit/duplicate/delete role buttons; clients-manager: drawer close, tab buttons, modal close buttons)
- [x] **W12 — Break up monolithic Blade files** — `add-news.blade.php` split into 9 modular partials under `partials/` — Commit `REFACTOR-002` (990 → ~30 line orchestrator + 9 partials: header, stepper, step1-write, step2-media, step3-organize, step4-review, preview-panel, modals, ai-drawer)
- [ ] **CSS consolidation** — Migrate custom CSS into Tailwind config (blocked by Q4)
- [ ] **Pint formatting** — Run `./vendor/bin/pint` across the codebase to fix pre-existing style issues (several flagged non-blocking during commits)
