# Design: Faithful Prototype Conversion — UNB Wire v1

**Status:** Draft → Awaiting Approval
**Author:** Architect (auto)
**Date:** 2026-08-29
**Inputs:** `app-data/README.md`, `app-data/*.html`, `v1-non-functional-requirements.md` §10/16, `v1-database-design.md` §10, `AGENTS.md` §6
**Related ADRs:** DEC-006 (No Filament, Blade+Livewire), DEC-007 (packages replace tiers), DEC-008 (field apps deferred)

---

## 1. Problem Statement & Requirements

### 1.1 Why faithful conversion is business-critical

Current `resources/views/livewire/admin/*` are **scaffold MWPs**: functional but not 1:1 with `app-data/*.html`. Gaps visible on `/admin/add-news`, `/admin` Dashboard KPIs, and chrome fidelity. Business impact: editorial trust, client SLA, and demo-to-prod audit all assume prototype look & behavior is the contract (app-data README §5, §17; NFR §16). Shipping scaffold = rework + churn.

### 1.2 Gap audit (prototype vs live)

| Prototype | Live today | Delta | Severity |
|-----------|------------|-------|----------|
| `index.html` — KPI tints, story table (status + distribution bar), top-clients, FAB, Dhaka live clock, topnav dropdowns | `dashboard.blade.php` — static KPIs, placeholder rows, `href="#"` on New Story (FIXED 2026-08-29), missing FAB/scroll | KPIs not wired, but chrome mostly done | **M** |
| `add-news.html` — 4-step wizard + stepper lines, bottom sticky wizard-nav, **Quill wire toolbar** (dateline/pull-quote/table/signoff/cleanup/find&replace/revision-history/fullscreen), word-level diff compare modal, **AI desk** (suggestion drawer, raw-vs-AI compare, touched markers, new-facts warning, publish gate), featured 1200x630 dashed preview (has-photo state), attach grid with g1-g8 gradients + rights select + bulk bar, doc-import modal, tags autocomplete, type chips, seg-control access (standard/exclusive) + distribution preview, **wf-strip** (status pill + owner avatar + shift + take-over), **nt-thread** with replies, live PV panel + collapse strip + fullscreen overlay with device toggles, autosave/restore banner, editor find bar, revision history | `livewire/admin/add-news.blade.php` — basic wizard (no stepper lines, no sticky nav), bare Quill (no wire toolbar), no find bar, no fullscreen, no featured image 1200x630 logic, media = simple library grid (no gradients/attach-tile semantics, no drop overlay/progress toasts), no doc-import, no type chips/seg-control/dist-preview, no wf-strip/take-over/shift, no nt-thread, no live preview collapse/fullscreen, AI desk = 2 buttons + inline pack (no drawer/compare/touched-markers) | **Heaviest delta — entire editorial UX lives here** | **H — P0** |
| `english-news.html` — status pills (In review/Needs work) owner line + notes badge, row → **workflow drawer** (status flow, takeover, notes thread) + filters/tabs with counts + pagination | `livewire/admin/news-list.blade.php` — basic table + filters | Missing drawer + status semantics | **H** |
| `unb-photo-manager.html` — justified grid, workflow tabs + alert counts, hover-reveal select, inspector panel, bulk bar + ZIP export, **Field intake** batch-grouped queue (per-photo/per-batch approve/reject/re-edit + reason modal) | `photo-manager.blade.php` — grid placeholder | Core DAM UX not wired | **H** |
| `ap-photo-manager.html` / `clients.html` / `packages.html` / `roles.html` / `ai-settings.html` | stubs/managers exist but chrome diffs | Tabs/drawers not 1:1 | **M** |
| `client-portal.html` | Deferred to Next.js track | — | — |

### 1.3 Must-preserve contracts (prototype wins on look, NFR wins on behavior)

- **Design tokens** `app-data/README.md §2` mapped 1:1 → `tailwind.config.js` `theme.extend.colors` (do not eyeball). Fonts: Fraunces/Inter/Source Serif 4 + Bangla conjunct-safe font. Icons: Lucide 24×24, stroke 1.8. Gradients g1–g8.
- **Shared chrome**: single `layouts/admin.blade.php` + `<x-sidebar>` + `<x-topnav>` (brand-strip gradient, navy sidebar sections Overview/Newsroom/Field apps/Distribution/Settings, sticky topnav with `/` search kbd + Dhaka clock + globe/bell/user dropdowns). No duplication per page.
- **Interaction contracts** `README §5`: 4-step wizard + draft autosave/restore, Quill wire toolbar, AI desk drawer/compare/gate, find-in-page, fullscreen, desk workflow strip, notes thread; news list drawer; photo manager tabs/inspector/bulk/ZIP/field intake; roles drawer/guard; AI settings localStorage contract `unb_ai_settings` until API replaces it; field-app queue/offline UX.
- **Storage/Cache rules**: Postgres `timestamptz`/`citext`, ULID `public_id`, monthly partitions; Meilisearch `main`/`archive`; Redis+Horizon; all per NFR §16.

### 1.4 Out of scope (locked deferred)

Field PWAs (`mojo-field-desk.html`, `photo-field-desk.html`) — keep prototypes as reference only, per DEC-008. Client portal Next.js runs parallel track, shares only API + tokens, not this plan.

---

## 2. Architecture & Component Boundaries

### 2.1 Stack lock

Laravel modular monolith — **MVC + service + repository** (NFR §16.1). Controllers/Livewire thin → Services own tx → Repositories own Eloquent → Models dumb. Livewire never calls internal REST API. Jobs call services.

### 2.2 Interactivity map (Livewire / Alpine / JS island)

| Behavior | Target | Notes |
|----------|--------|-------|
| List filtering, tabs with counts, pagination, autosave draft | **Livewire** (URL-synced, server state) | debounced `updated` → `StoryService` |
| Workflow drawer / inspector (data-bearing) | **Livewire lazy** | `wire:lazy` + service fetch |
| Modals that hit DB (reject/re-edit, auto-publish confirm) | **Livewire** | dispatch + service |
| Visual toggles: dropdowns, preview collapse, drawer animation, stepper visual | **Alpine** | no round-trip |
| Quill + wire toolbar + find bar + fullscreen + word-level diff + gradient thumbs + g1-g8 + find-in-page + canvas ops | **JS islands** wired via `dispatch`/`$wire` | keep as modules under `resources/js/editor/*` |
| Autosave | Livewire `updated` debounced 800ms → `StoryService::autosave` | restore banner on load |
| Intake badge live | Reverb → Livewire event | per NFR §16 |

### 2.3 Component inventory (build once, props-driven)

Per README §12: `<x-btn>` (variant+size), `<x-card>`, `<x-pill>` (wf-pill/status/cat-tag), `<x-modal>` (+ `<x-ai-gate>`), `<x-drawer>` (ai-drawer/wfd/role editor), `<x-tabs>` (wf-tabs/pg-tab counts), `<x-data-table>` (sticky header, pagination), `<x-switch>`, `<x-dropdown>`, `<x-toast>` (global, replaces per-page mini-toasts), `<x-workflow-strip>`, `<x-note-thread>`, `<x-filter-bar>`, `<x-thumb>` (g1–g8). All live in `resources/views/components/`.

### 2.4 Page-to-target map

| Prototype | Route(s) | Blade | Livewire | JS island | Service touches |
|-----------|----------|-------|----------|-----------|-----------------|
| `index.html` | `GET /admin` | `admin/dashboard.blade.php` | optional `DashboardStats` (or inline service read) | `topnav.js` (clock, dropdowns) | `StatsService`, `StoryRepository` (eager, cached) |
| `add-news.html` | `GET /admin/add-news` (also edit ` /admin/story/{id}/edit`) | `admin/add-news.blade.php` thin wrapper | `AddNews` (already exists — **refactor to faithful**) | `editor/quill-wire.js`, `editor/find-bar.js`, `editor/diff.js`, `media/drop-overlay.js`, `preview/live-preview.js` | `StoryService`, `RevisionService`, `MediaService`, `AiDeskService`, `TagService` |
| `english-news.html` (+ bn mirror) | `GET /admin/news/{lang}` | `admin/news.blade.php` | `NewsList` + `WorkflowDrawer` | — | `StoryRepository` (partition-aware), `StoryService::transition` |
| `unb-photo-manager.html` | `GET /admin/photos` | `admin/photos.blade.php` | `PhotoManager` + `Inspector` + `FieldIntakeQueue` | `photo/justified-grid.js` | `MediaService` |
| `ap-photo-manager.html` | `GET /admin/ap-photos` | `admin/ap-photos.blade.php` | `ApPhotoManager` | — | `MediaService` (AP source) |
| `clients.html` | `GET /admin/clients` | existing | `ClientsManager` — chrome pass | — | `ClientService` |
| `packages.html` | `GET /admin/packages` | existing | `PackagesManager` — chrome pass | — | `PackageService` |
| `roles.html` | `GET /admin/roles` | existing | `RolesManager` — drawer/guard polish | — | `RbacService` |
| `ai-settings.html` | `GET /admin/ai-settings` | existing | `AiSettings` — keep `localStorage` contract | `ai/settings-bridge.js` | `SettingsService` |
| `english-service.html` / `delivery-settings.html` | `GET /admin/service/{s}` / `.../delivery-settings` | restyle to current chrome | `ServiceConfig` / `DeliverySettings` | — | `DistributionService` |

---

## 3. Data Model & Schema Impact

**No new tables.** Schema is complete per `v1-database-design.md` §10 (migrated 2026-08-27). Faithful conversion reads/writes existing tables via repos; adds no migrations except optional `story_drafts` JSON if we want server autosave separate from `stories` (recommend keeping `stories.status=draft` + `story_versions` + `index_outbox` outbox row in same tx).

Constraints to enforce in conversion: `timestamptz`, `citext` citext on email, ULID `public_id`, FK `role_id` on users, `stories` FKs to `categories`/`users`, `story_media` pivot, `deliveries` partitioned by `scheduled_at`.

---

## 4. API / Service Contracts

### 4.1 Services consumed (NFR §15 inventory)

`StoryService::create/update/transition/takeOver/publish`, `RevisionService::snapshot`, `MediaService::attach/detach/batchUpdateRights`, `TagService::suggest/attach`, `AiDeskService::preedit/tags/translate/generate` (initially `localStorage('unb_ai_settings')` gate, then `POST /api/ai/{kind}`), `DistributionService::previewPlan`, `RbacService::assertCan`.

### 4.2 Livewire ↔ JS events

- `dispatch('ai-applied', html)` → Quill replace
- `dispatch('draft-restored')` → banner
- `dispatch('media-attached', ids)` → preview thumbs update
- `store.js` (Alpine) for preview device toggle + collapsed state (persists `localStorage('unb_pv_collapsed')`)

### 4.3 Validation

Use **Form Request / Livewire `rules()`** per task: headline required, brief ≤280, category FK, dateline city, priority enum(`routine,urgent,flash`), embargo explicit-tz (store UTC, render Dhaka), body HTML purified (strip unsafe tags), story_media rights enum.

---

## 5. Security, RBAC & Entitlements

- Every route already guarded: `['auth','verified','rbac:<module>,<action>']` (see `routes/web.php`). Faithful conversion **keeps** `RbacService.assertCan` server-side; UI hiding alone is not authorization.
- Internal notes never leak to client API/feeds/search (`story_notes` flagged `is_internal`).
- Purify rich text before DB insert (`HTMLPurifier` / `clean()` in `StoryService`).
- Rate-limit staff POST endpoints (`throttle:60,1`). Client keys remain hashed, scoped, per-key limit.
- No secrets in repo; image caps validated by MIME+ext+size; presigned URLs only for private media.

---

## 6. Task Decomposition Outline (Planner handoff)

**Build order = README §11** (foundation → simple pages → lists → DAM → wizard last) + ACT-DP budgets (≤3k tokens/task, one endpoint or one drawer per task).

| # | Task ID | Title | Depends | What ships | Verify |
|---|---------|-------|---------|------------|--------|
| 0 | `M8-PLAN-001` | This design doc + task board update | — | this file + `docs/tasks/README.md` row | reviewer sign-off |
| 1 | `M8-FOUND-001` | Tailwind tokens pass: `tailwind.config.js` + fonts + g1–g8 + icon set | 0 | `tailwind.config.js` colors, `resources/css/app.css` g1–g8, font imports | Visual: 1440/1920 side-by-side vs prototype §2 |
| 2 | `M8-FOUND-002` | Shared chrome audit: `admin.blade.php` + `<x-sidebar>` + `<x-topnav>` + `<x-toast>` unification | 1 | single layout, no per-page duplication, FAB where needed | Chrome test: all admin routes 200 as Admin/Editor |
| 3 | `M8-UI-001` | Component library pass #1: `<x-btn>` `<x-card>` `<x-pill>` `<x-tabs>` `<x-modal>` `<x-drawer>` `<x-switch>` `<x-dropdown>` `<x-thumb>` | 2 | `resources/views/components/*` | Storybook-style preview route `/__preview` |
| 4 | `M8-UI-002` | Component library pass #2: `<x-workflow-strip>` `<x-note-thread>` `<x-filter-bar>` `<x-data-table>` | 3 | same | — |
| 5 | `M8-DASH-001` | Dashboard faithful: KPI tints + live data + story table + top-clients + FAB + Dhaka clock | 2 | `admin/dashboard.blade.php` wired to `StoryRepository`/`ClientService` (cached, eager) | Live smoke: `/admin` as Admin, `php artisan test` |
| 6 | `M8-NEWS-001` | News list faithful (en+bn): filters, tabs with counts, pagination, row states | 3 | `NewsList` Livewire | — |
| 7 | `M8-NEWS-002` | Workflow drawer: status flow, owner+shift, take-over (optimistic lock 409), notes thread + replies | 6 | `WorkflowDrawer` lazy Livewire | RBAC test: Uploader can't publish |
| 8 | `M8-PHOTO-001` | UNB Photo Manager: justified grid + workflow tabs/alerts + hover-reveal + inspector + bulk/ZIP | 3 | `PhotoManager` | — |
| 9 | `M8-PHOTO-002` | Field intake queue: batch-grouped approve/reject/re-edit + reason modal + Reverb badge | 8 | `FieldIntakeQueue` | reason required (422) test |
| 10 | `M8-SIMPLE-001` | Simple pages chrome-faithful: clients/packages/roles/ai-settings/service/delivery-settings | 3 | style pass + drawer/guard fix | roles: delete guard test |
| 11 | `M8-WIZ-001` | Wizard shell: stepper with lines + sticky wizard-nav + autosave/restore banner + draft→`stories` tx | 2,3 | `AddNews` step 1 polish + autosave | autosave restore test |
| 12 | `M8-WIZ-002` | Body step: Quill wire toolbar (dateline/pull-quote/table/signoff/cleanup/find&replace/rev-history) + find bar + fullscreen | 11 | `resources/js/editor/quill-wire.js` | fullscreen + find E2E |
| 13 | `M8-WIZ-003` | Featured image 1200×630 + attach grid g1–g8 + rights select + bulk bar + drop overlay + progress toasts + doc-import modal | 11 | same | drag-drop E2E |
| 14 | `M8-WIZ-004` | Tags/Type chips/Seg-control/Distribution preview + live PV panel + collapse strip + overlay+device toggles | 11 | `preview/live-preview.js` | device widths snapshot |
| 15 | `M8-WIZ-005` | Desk workflow strip + internal notes thread (add/reply, immutable) + take-over/shift handover | 11 | `<x-workflow-strip>` + `<x-note-thread>` wired | take-over 409 test |
| 16 | `M8-WIZ-006` | AI desk faithful: drawer + per-card apply + raw-vs-AI diff modal (word-level) + touched markers + new-facts warning + publish gate + kill switch + token budget | 11 | `AiDeskService` bridge, `ai/settings-bridge.js` | kill-switch blocks call (403) |
| 17 | `M8-E2E-001` | Wizard E2E: draft → review → rework → approve → publish + kill/correction fan-out spot | 11–16 | Playwright `tests/e2e/wizard.spec.ts` | `php artisan test` + Playwright green |
| 18 | `M8-QA-001` | A11y + i18n + perf: WCAG on wizard, Bangla NFC/conjunct font, N+1 audit, pagination, cache invalidation | 17 | — | axe-core + `Model::preventLazyLoading` in non-prod |

**Per-task quality gates (all apply):** `php -l`, `php artisan test`, `migrate:fresh --seed` clean, eager-load audit, RBAC asserts, live smoke per `docs/workflow/live-test-runbook.md` (caches cleared, routes hit as Admin/Editor/Uploader), `unb-wire-reviewer` review, conventional commit `feat(add-news): ... [M8-WIZ-00N]`.

---

## 7. Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Wizard scope creep (Quill + preview + media in one PR) | Split into 6 wire tasks (WIZ-001…006), each ≤3k tokens, single responsibility |
| JS island drift (Alpine vs Livewire state) | Publish event contracts above; no ad-hoc fetch — all via `$wire` + `dispatch` |
| Token / style drift | Tokens are law: diff `tailwind.config.js` vs README §2 on every PR |
| RBAC regression | Every task includes 403 test + live smoke as Editor/Uploader |
| Perf (N+1, feed cache) | `preventLazyLoading` in dev, repository-only queries, tag invalidation in service |

---

## 8. Acceptance Criteria (plan done when)

- `docs/tasks/README.md` board lists M8-* rows (Pending→In Progress as coded)
- Each M8 task file exists per ACT-DP (§4 template) before coding
- Heaviest page `add-news` passes `docs/plans` §17 checklist: side-by-side 1440/1920 visual match, every §5 bullet works, FRs cited pass, no demo affordances leak, Bangla NFC fixture renders

---

## 9. Next Step

1. Approve this doc → 2. Planner creates `docs/tasks/M8-*-*.md` per §6 outline → 3. Coder ships in build order, smallest page first, wizard last (README §11). If approved, I can generate the full task files now.
