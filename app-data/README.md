# UNB Wire — UI Prototype → Production Conversion Guide

This folder contains the **approved UI/UX prototypes** for UNB Wire v1 (news agency
platform: newsroom admin, photo DAM, client portal, field apps). These are plain
hand-written HTML/CSS/JS files — **no build step, no framework**. They are the visual
and interaction contract. Your job as the converting agent: translate them faithfully
into the target stack, per surface, defined below.

**Read in this order:**
1. This README (what to build, where each file maps)
2. `v1-non-functional-requirements.md` (the engineering contract — see §10 below)
3. `v1-database-design.md` (the full PostgreSQL schema — conventions, every table,
   partitioning, and the ordered migration task list you should follow)
4. `v1-functional-requirements.md` (the FRD — what to build, with acceptance
   criteria; complete: NWS / AI / MED / FLD / DST / CLT / PRT / ACC / NTF)

---

## 1. File map — what each prototype is, and its target stack

Target architecture (locked, NFR §16): **Laravel modular monolith** backend;
**Blade + Livewire** admin; **Next.js** client portal; **static PWA** field apps.
Backend layering (locked, NFR §16.1): **MVC + service + repository** — controllers
and Livewire components are thin, §15 services own all business logic + transactions,
repositories are the only place Eloquent is used, models stay dumb.

| File | Surface | Convert to | Notes |
|---|---|---|---|
| `index.html` | Admin dashboard | Blade + Livewire | desktop-first |
| `add-news.html` | Add News wizard (4 steps) + AI desk | Blade + Livewire + Alpine | heaviest page — see §6 |
| `english-news.html` | English news list | Blade + Livewire | reference list page |
| `clients.html` | Client management | Blade + Livewire | |
| `packages.html` | Subscription packages | Blade + Livewire | |
| `unb-photo-manager.html` | UNB Photos DAM + **field intake approval queue** | Blade + Livewire | justified photo grid |
| `ap-photo-manager.html` | AP Photo Manager | Blade + Livewire | |
| `roles.html` | RBAC management | Blade + Livewire | drawer + modals |
| `ai-settings.html` | AI feature flags, token budget, kill switch | Blade + Livewire | reads/writes `unb_ai_settings` |
| `english-service.html`, `delivery-settings.html` | Service/distribution config | Blade + Livewire | older structure, no sidebar sections — restyle to current chrome |
| **`v1-database-design.md`** | **PostgreSQL schema + migration task list** | **Laravel migrations** | not a page — the DB contract; build order = its §10 |
| **`v1-functional-requirements.md`** | **FRD — feature requirements + acceptance criteria** | — | not a page; IDs like FR-NWS-010, MoSCoW priorities |
| `story.html` | Single story view | Blade + Livewire | older structure |
| **`client-portal.html`** | **Client portal** | **Next.js (CDN-hosted)** | biggest consumer of news — see §7 |
| **`mojo-field-desk.html`** | **MoJo field app** | **Static PWA** (keep HTML, add service worker + IndexedDB) | offline-first, mobile |
| **`photo-field-desk.html`** | **Photojournalist field app** | **Static PWA** | offline-first, mobile |

**Bangla News section: mirror English News exactly.** Same list page, same Add News
wizard, same AI desk — only language/content differs, plus Bangla font handling and
the Bangla→English AI button already present. Do not design a separate Bangla UI.

---

## 2. Design tokens (must survive conversion)

All pages share these CSS custom properties. Map them 1:1 into the Tailwind theme
(`tailwind.config.js → theme.extend.colors`) — do not eyeball hex values:

```css
--navy-900: #0f1730;   --navy-800: #16204a;   --navy-text: #aab3cf;  --navy-label: #667099;
--crimson: #e5484d;    --crimson-dark: #d13438; --crimson-soft: #fdecec;
--amber: #f0a832;      --paper: #faf9f6;       --panel: #ffffff;      --border: #eceae5;
--ink: #1c1f2e;        --muted: #7c7f8c;       --muted-2: #b0b2bc;
--green: #16a34a;      --green-bg: #e5f6ec;
--blue: #3b6fe0;       --blue-bg: #e9f0fd;
--purple: #7c3aed;     --purple-bg: #f1eafe;   /* AI features */
--serif: "Fraunces", Georgia, serif;           /* headings, h1, card titles */
--sans: "Inter", system-ui, sans-serif;        /* UI text */
```

Also: 8 gradient classes `.g1`–`.g8` used as photo-thumbnail placeholders (see
`photo-field-desk.html` / `unb-photo-manager.html` for values). Purple (`--purple`)
is reserved for **AI-related UI** everywhere — keep that association.

**Typography:** Google Fonts `Fraunces` (display/headings), `Inter` (UI),
`Source Serif 4` (article/body serif in editor surfaces). Bangla pages need a
Bengali-capable web font with conjunct-safe rendering (NFR §10).

**Icons:** inline SVG, 24×24 viewBox, `stroke-width: 1.8`, round caps — Lucide/Feather
style. Keep the same icon set (Lucide icons map 1:1).

---

## 3. Responsive strategy

- **Admin dashboard = desktop-first.** Designed for 1366–1920px. Sidebar 240px,
  sticky topnav, main content max ~1200px where reading matters (forms), full-width
  where data-dense (DAM grid, tables). Must hold up at 1920px+ (big screens) without
  absurd stretching — cap content columns, let grids breathe. Below ~900px the
  prototype collapses the sidebar (`display:none`) — in production, replace with a
  proper drawer nav; admin mobile use is out of scope for v1.
- **Field apps = mobile-first.** `.field-app` is a 680px-centered column — that is
  intentional (phone-first); do not stretch them to desktop width.
- **Client portal = responsive both ways** (sub-editors on laptops, some on tablets).

Tailwind breakpoints: use `lg` (1024) as the admin baseline, design at `xl` (1280),
verify at `2xl` (1536+).

---

## 4. Shared chrome (admin pages)

Every admin page shares: `.brand-strip` (4px top gradient), navy `.sidebar`
(sections: Overview / Newsroom / Field apps / Distribution / Settings), sticky
`.topnav` (search with `/` kbd, live Dhaka clock, globe → client portal, bell + user
dropdowns). Convert these into **one Blade layout + sidebar component** — do not
duplicate per page. "Field apps" sidebar links open in new tabs (they are separate
surfaces).

---

## 5. Interaction contracts that must survive conversion

These behaviors are deliberate — do not flatten them:

- **add-news.html:** 4-step wizard with draft autosave/restore banner, Quill editor
  (custom wire toolbar: dateline, pull quote, table, signoff, cleanup, find & replace,
  revision history), **AI desk** (see §6), publish gate for AI-touched content,
  find-in-page bar, fullscreen editor, **desk workflow strip** (status pill, owner +
  shift, take-over, send-to-editor), **internal notes thread** in the review step.
- **english-news.html:** workflow statuses (In review / Needs work) with owner line
  and notes badge; row opens the **workflow drawer** — status flow, owner + take-over
  (shift handover), internal notes thread with replies.
- **unb-photo-manager.html:** workflow tabs with alert counts, justified photo grid,
  hover-reveal selection, inspector side panel, bulk bar, ZIP export, **Field intake
  tab** (batch-grouped approval queue with per-photo and per-batch approve / reject /
  re-edit with reason modal).
- **roles.html:** permission matrix drawer, system-role lock, member-count guard on
  delete, audit list.
- **ai-settings.html:** toggles persist to `localStorage('unb_ai_settings')`;
  auto-publish requires modal confirmation; kill switch. add-news.html **reads this
  key** — keep the contract until the API replaces it.
- **Field apps:** bottom-tab navigation, online/offline toggle with send queue,
  simulated desk responses on timers (keep as mock services).

---

## 6. AI desk — integration points (add-news.html)

The AI feature is **structure-complete, API-pending**. One stub to replace:

```js
UNBAI._call(kind, payload)  // kind: 'preedit' | 'tags' | 'translate' | 'generate'
// → replace with fetch('/api/ai/' + kind, …); the drawer, compare view, publish
//   gate and touched-markers need no changes.
```

Preserve: suggestion drawer (right slide-in), per-card apply, **raw vs AI side-by-side
compare modal** (word-level diff), "✦ AI — unreviewed" field markers (cleared by human
edits), new-facts warning, publish checklist gate. Behavior spec: NFR §14.

---

## 7. Client portal (Next.js) notes

Highest-traffic human surface — client experience is revenue. Requirements:
- Browser-direct **Meilisearch with tenant tokens** (entitlement filter baked into the
  token) — search never hits Laravel.
- Per-package-tier CDN caching; published stories are immutable.
- Presigned URLs for downloads.
- Convert `client-portal.html` only; keep visual identity from §2 tokens.

---

## 8. localStorage contracts (demo-era, replaced by API later)

| Key | Written by | Read by |
|---|---|---|
| `unb_ai_settings` | `ai-settings.html` | `add-news.html` (desk kill-switch, auto-publish gate) |
| `unb_draft_v1` / `unb_rev_v1` | `add-news.html` | same (autosave + revisions) |

Keep these working in converted pages until backend endpoints exist.

## 9. Conventions

- All demo data is seeded in-file (arrays at top of each script) — replace with API
  calls, keep the data shapes.
- Timestamps use `Intl` with `timeZone: 'Asia/Dhaka'` — business timezone is Dhaka,
  storage is UTC; portal renders client-local (NFR §11).
- Toasts/mini-toasts per page — consolidate into one Blade/Livewire notification
  component.
- Bangla content: NFC-normalize on ingest (NFR §10); test with real Bangla text.

## 10. How to use the NFR doc (`v1-non-functional-requirements.md`)

It is the **engineering contract**, organized so you can build subsystem-by-subsystem:

- **§0 Load model** — size your infra and seed data realistically.
- **§1 Search** — Meilisearch everywhere, `main` + `archive` indexes, the 4-layer
  Bangla strategy (stemmer-light, synonyms, AI English tags, uploader-confirm).
  The Bangla spike test in §1 is the **first engineering task** — it gates the search design.
- **§4 Archival** — hot/warm/cold tiers; >12-month content moves out of the hot path.
- **§11 Time & timezone** — UTC storage, Dhaka for staff, client-local for portal,
  explicit-tz embargoes. Uploaders are in Bangladesh; consumers are worldwide.
- **§6–7 Distribution + async jobs** — the worker inventory is your queue/jobs list;
  nothing slow inside request/response. Laravel: Horizon + scheduler.
- **§14 AI** — token budgets, kill switch, auto-publish guardrails, confidentiality
  (zero-retention agreement is a hard requirement before sending unpublished stories
  to any LLM).
- **§15 Shared services** — implement these as Laravel service classes; both Livewire
  components and API controllers call them. Never let Livewire call the internal REST API.
- **§16 Stack** — locked decisions. **§17 Deferred** — do not build these.
- **Open questions** (end of doc) — escalate to the product owner before v1 freeze.

When a prototype and the NFR disagree, the NFR wins on behavior, the prototype wins on
look & feel.

---

## 11. Conversion order (do not skip steps)

1. **Foundation:** Tailwind config from §2 tokens; fonts; Lucide icon set; the one
   Blade layout + sidebar/topnav components from §4.
2. **Shared component library** (§12) — build once, Storybook-style preview page,
   verify against the prototypes side by side.
3. **Simple pages first** (clients, packages, roles) to prove the pattern; then
   lists (english-news), then DAM (unb-photo-manager), then the wizard
   (add-news) last — it consumes everything else.
4. **Portal** (Next.js) and **field apps** (PWA) can run in parallel tracks —
   they share only the API.

## 12. Component inventory (build once as Blade components)

Recurring primitives across all admin pages — one component each, props-driven:

| Prototype class(es) | Blade component | Notes |
|---|---|---|
| `.btn`, `.btn-primary/-navy/-outline/-quick`, `.btn-sm` | `<x-btn>` | variant + size props |
| `.card`, `.card-title` | `<x-card>` | |
| `.wf-pill`, `.status-label`, `.cat-tag`, `.note-badge`, `.nav-badge` | `<x-pill>` | color/variant prop |
| `.modal`, `.ai-gate`, reason modals | `<x-modal>` | teleport, Esc-to-close |
| `.ai-drawer`, `.wfd`, role editor drawer | `<x-drawer>` | right slide-in |
| `.wf-tabs`, `.pg-tab` | `<x-tabs>` | count badges |
| tables + `.pagination` | `<x-data-table>` | sticky header variant |
| `.switch` | `<x-switch>` | |
| `.drop` dropdowns (bell/user) | `<x-dropdown>` | |
| mini-toasts per page | `<x-toast>` (single, global) | replaces all local copies |
| `.wf-strip` | `<x-workflow-strip>` | |
| `.nt-*` notes thread | `<x-note-thread>` | reused by add-news + workflow drawer |
| `.filters`, `.search-input` | `<x-filter-bar>` | |
| gradient thumbs `.g1`–`.g8` | `<x-thumb>` | placeholder until real derivatives |

## 13. Interactivity mapping — Livewire vs Alpine vs plain JS

| Behavior in prototype | Convert to |
|---|---|
| List filtering, tabs with counts, pagination | **Livewire** (server state, URL-synced) |
| Workflow drawer, inspector panel (data-bearing) | **Livewire** lazy-loaded component |
| Modals that trigger server actions (reject/re-edit, auto-publish confirm) | **Livewire** |
| Pure visual toggles: dropdowns, preview collapse, drawer open/close animation, stepper visual state | **Alpine** (no server round-trip) |
| Quill editor, word-level diff, canvas gradient thumbs, find-in-page | **plain JS** modules, wired via Livewire `dispatch`/events |
| Autosave drafts | Livewire `updated` hook, debounced → service call |
| Field-intake badge live updates | **Reverb** websocket → Livewire event (NFR §16) |

Rule of thumb: **if it reads or writes server data → Livewire; if it only moves
pixels → Alpine; if it is a self-contained editor/algorithm → plain JS island.**

## 14. Demo state → production API mapping

| Prototype demo mechanism | Production replacement |
|---|---|
| `localStorage('unb_ai_settings')` | `settings` table → served to the form at load (FR-AI-007) |
| `localStorage('unb_draft_v1')` autosave | server-side drafts on `stories` (FR-NWS-002) |
| `localStorage('unb_rev_v1')` | `story_versions` via `RevisionService` |
| seeded arrays at top of each script | API/Livewire props — **keep the data shapes** |
| `UNBAI._call` stub | `POST /api/ai/{kind}` → `PreeditOrchestrator` (see §6) |
| field-app simulated desk timers | keep behind a `DeskService` JS interface in the PWA; swap internals when the API lands — the queue/offline UX is the product |
| `WF` / `WF_STORIES` workflow objects | `GET/PATCH /api/v1/stories/{id}/workflow`, `POST …/notes` (contracts are in the code comments) |

## 15. Next.js portal recipe

- App Router; server components for the feed shell, client components only where
  interactive (search box, filters, download buttons).
- Meilisearch JS client in the browser with tenant token from
  `POST /api/v1/portal/search-token` (short-lived) — search never proxies through
  Next.js either.
- Feed pages: ISR with per-package-tier cache tags; story detail = static after
  publish (immutable), revalidated on correction.
- Times render client-local with Dhaka secondary (FR-PRT-007).

## 16. Field-app PWA recipe

- Keep the prototype HTML nearly as-is; add `manifest.webmanifest`, service worker
  (app-shell cache), IndexedDB outbox implementing the send-queue contract
  (FR-FLD-001/002).
- All network access behind one `ApiClient` module with token refresh + device id;
  never fetch ad-hoc from views.

## 17. Per-page verification checklist (before calling a page done)

1. Visual: side-by-side with the prototype at 1440px and 1920px — tokens, spacing,
   typography match (§2 is law).
2. Every behavior in §5's bullet for that page works.
3. All FRs citing this page pass their acceptance criteria.
4. No demo affordances leak (see §18).
5. Bangla text renders with real Bangla fixtures, NFC-clean (NFR §10).

## 18. Do-not-build list (prototype-only scaffolding)

- "Add 3 demo frames" button; any `demoBtn`-style affordance
- simulated `setTimeout` desk outcomes in field apps (keep the queue, not the dice)
- hard-coded seed arrays as the data source
- the `.field-app` netToggle as a real network control (it is a UX demo of offline mode)

