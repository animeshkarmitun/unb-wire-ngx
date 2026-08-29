# UNB Wire Prototype Fidelity Audit

**Date:** 2026-08-29
**Method:** Side-by-side comparison of every `app-data/*.html` prototype against the current Laravel/Blade/Livewire implementation.
**Definition of terms:**
- **Full rebuild** — the page/component needs to be rethought or rewritten from scratch to match the prototype.
- **Partial rebuild** — layout and data layer are mostly usable, but significant interactions/sections are missing or wrong.
- **Polish** — small missing features or styling gaps; the core prototype contract is largely met.
- **Not built** — no production implementation exists.

---

## Executive summary

Most admin surfaces are **scaffold-only**: they share the chrome and basic CRUD, but the prototype’s interactions — upload/drop, workflow drawers, bulk actions, preview overlays, AI drawer, field intake, package builder, etc. — are missing. Only the shared layout and a few simple pages are close to done.

| Segment | Prototype | Implementation | Verdict | Ticket / area |
|---|---|---|---|---|
| Shared admin chrome | `index.html` chrome | `layouts/admin.blade.php`, `components/sidebar.blade.php`, `components/topnav.blade.php` | Polish | M8-FOUND |
| Dashboard | `index.html` body | `admin/dashboard.blade.php` | Partial rebuild | M8-DASH |
| English News list | `english-news.html` | `livewire/admin/news-list.blade.php` + `NewsList.php` | Partial rebuild | M8-NEWS |
| Add News wizard | `add-news.html` | `livewire/admin/add-news.blade.php` + `AddNews.php` | **Full rebuild** | M8-WIZ |
| UNB Photo Manager | `unb-photo-manager.html` | `livewire/admin/photo-manager.blade.php` + `PhotoManager.php` | **Full rebuild** | M8-PHOTO |
| AP Photo Manager | `ap-photo-manager.html` | `livewire/admin/ap-photo-manager.blade.php` + `ApPhotoManager.php` | Partial rebuild | M8-AP |
| Clients | `clients.html` | `livewire/admin/clients-manager.blade.php` + `ClientsManager.php` | Partial rebuild | M8-CLT |
| Packages | `packages.html` | `livewire/admin/packages-manager.blade.php` + `PackagesManager.php` | Partial rebuild | M8-PKG |
| Roles & access | `roles.html` | `livewire/admin/roles-manager.blade.php` + `RolesManager.php` | Partial rebuild / polish | M8-ACC |
| AI settings | `ai-settings.html` | `livewire/admin/ai-settings.blade.php` + `AiSettings.php` | Partial rebuild | M8-AI |
| Story view | `story.html` | `admin/story.blade.php` + `StoryView.php` | Partial rebuild | M8-STORY |
| English Service | `english-service.html` | `livewire/admin/service-config.blade.php` + `ServiceConfig.php` | **Full rebuild** | M8-SRV |
| Delivery settings | `delivery-settings.html` | `livewire/admin/delivery-settings.blade.php` + `DeliverySettings.php` | **Full rebuild** | M8-DST |
| Distribution log | — | `livewire/admin/distribution-log.blade.php` + `DistributionLog.php` | Partial rebuild | M8-DST |
| Client portal | `client-portal.html` | `portal/` (Next.js) | Partial rebuild | M8-PRT |
| MoJo field desk | `mojo-field-desk.html` | Not implemented | **Not built** | M8-FLD |
| Photo field desk | `photo-field-desk.html` | Not implemented | **Not built** | M8-FLD |

---

## 1. Add News wizard — FULL REBUILD

**Files:** `app-data/add-news.html` vs `resources/views/livewire/admin/add-news.blade.php` + `app/Livewire/Admin/AddNews.php`

### Why full rebuild
The current wizard has the wrong step architecture and most interactions are placeholders.

- **Step labels are wrong.** Prototype: Write → Media → Organize & access → Review & publish. Current: Basics → Body → Media → Review.
- **Step 1 content is wrong.** Prototype step 1 is headline/sub/brief plus the **Quill editor with wire toolbar**. Current step 1 puts headline/brief/category in a form and adds a non-functional featured-image dropzone.
- **Media is in the wrong step.** Featured image + attached media belong in prototype step 2; current implementation places them in step 3 and step 1.
- **Organize & access step is missing entirely.** No category/subcategory/author/tags/type-chips/seg-control/distribution-preview.
- **Quill wire toolbar is incomplete.** Missing dateline, pull quote, also-read, section break, find & replace bar, revision history, fullscreen, word/read-time footer.
- **Live preview is a box, not a panel.** Missing collapse strip, fullscreen overlay, device-width toggles (760/600/392).
- **AI desk is not a drawer.** Missing start-with-AI card, suggestion drawer, side-by-side diff, publish gate, touched field markers.
- **No desk workflow strip.** Missing status pill, owner, take-over, send-to-editor.
- **Review step has no review rows or notes thread.**
- **Upload/drag-drop is inert.** No `WithFileUploads`, no progress toast, no drop overlay.
- **Doc import (.docx) is missing.**

### What to keep
- Autosave hooks, `StoryService` calls, RBAC gates, and the basic `bodyHtml` sync pattern can be reused.

### Effort
Largest single surface. Plan 6–8 focused PRs per `docs/plans/remediation-fix-all-v2.md` P2–P7.

---

## 2. UNB Photo Manager — FULL REBUILD

**Files:** `app-data/unb-photo-manager.html` vs `resources/views/livewire/admin/photo-manager.blade.php` + `app/Livewire/Admin/PhotoManager.php`

### Why full rebuild
The current component is a stub that shares the page name and a card grid. Almost every prototype interaction is missing or broken.

- **Wrong grid.** Prototype uses a true justified flex grid with per-item `--r` aspect ratios; current uses fixed `aspect-[4/3]` cards.
- **Wrong tabs.** Prototype: All assets / Needs review / Field intake / In library / Packaged / Published / Embargoed with alert counts. Current: Library / Field / Re-edit with only one alert.
- **Missing hover-reveal selection.** Checkbox is always visible.
- **Missing status pills, stack/burst expansion, video duration badges on cards.**
- **Inspector is wrong.** Current is a simple slide-over. Prototype has a sticky inspector with status pill, editable caption/photographer/location/keywords/package, attached-story link, and client usage stats.
- **Bulk bar incomplete.** Missing package assignment and ZIP export.
- **Field intake queue is incomplete.** Missing per-photo reject, batch-level request re-edit / reject, urgency styling, photographer avatar, wait-time, live indicator.
- **Reject/re-edit reason modal missing.**
- **Upload/drag-drop incomplete.** No aspect-ratio calculation, no “Needs review” landing state, no progress toasts.
- **Filters incomplete.** Missing photographer, category, sort, and “Unattached only” toggle.
- **Search only hits title.** Prototype searches caption, photographer, location, keywords.
- **Logic bugs:** `render()` always queries paginated assets even on the Field tab; `approve()` always sets status to `library` regardless of source.

### What to keep
- `MediaAsset` / `MediaBatch` models, `MediaService::store` MIME/size checks, and the field-batch query can be retained after fixing status logic.

### Effort
Second-largest admin surface. Needs view re-architecture + small service additions (ZIP, reason tracking, package assignment).

---

## 3. English Service page — FULL REBUILD

**Files:** `app-data/english-service.html` vs `resources/views/livewire/admin/service-config.blade.php` + `app/Livewire/Admin/ServiceConfig.php`

### Why full rebuild
The prototype is a public-facing service home page (masthead, category nav, ticker, hero story, section grids, rail tabs, footer). The current implementation is a tiny admin settings form with wire name, description, and an enabled toggle.

- **No public page layout.**
- **No category navigation, ticker, hero, section grids, rail tabs, footer.**
- **No story rendering for the service feed.**

### Effort
Rebuild as a public service page or split into a frontend route plus admin config. Medium-large.

---

## 4. Delivery settings page — FULL REBUILD

**Files:** `app-data/delivery-settings.html` vs `resources/views/livewire/admin/delivery-settings.blade.php` + `app/Livewire/Admin/DeliverySettings.php`

### Why full rebuild
The prototype is a client-facing delivery configuration page (auto-push FTP/SFTP credentials, channel toggles, wire format, push schedule, API key reveal/regenerate, webhook URL, email alert chips, download/license history). The current admin implementation only shows retry/backoff/auto-pause/at-least-once toggles.

- **No client channel config UI.**
- **No credential reveal/regenerate/test connection.**
- **No push schedule, wire format selector, email alert chips.**
- **No download/license history table.**

### Effort
Medium. The backend distribution logic exists; the UI is missing.

---

## 5. English News list — PARTIAL REBUILD

**Files:** `app-data/english-news.html` vs `resources/views/livewire/admin/news-list.blade.php` + `app/Livewire/Admin/NewsList.php`

### Gaps
- **Filters changed.** Prototype has search + category + status select + Search button. Current uses status tabs and drops `Live`, `Needs work`, `killed`, `archived`.
- **Table columns missing.** Sub category, Views, row checkbox, thumbnail color blocks, category color tags.
- **Status display wrong.** Prototype uses toggle switch + Live/Draft label; current uses static pill.
- **Row metadata missing.** Owner line/avatar, note badge, Edit/Delete actions.
- **Workflow drawer is a generic detail drawer.** Missing workflow step visualization, owner card, Take over button, styled notes thread with reply input.
- **Pagination uses default Laravel links** instead of prototype numbered buttons + results info.

### What to keep
Data query and lazy-loading skeleton can stay; Blade view and workflow methods need rewrite.

### Effort
Medium.

---

## 6. AP Photo Manager — PARTIAL REBUILD

**Files:** `app-data/ap-photo-manager.html` vs `resources/views/livewire/admin/ap-photo-manager.blade.php` + `app/Livewire/Admin/ApPhotoManager.php`

### Gaps
- **Topbar missing.** No Sync log / Sync now / last-synced note.
- **Filters incomplete.** Missing category/subcategory/tag inputs, Reset, category chips.
- **Result count missing.**
- **Cards wrong.** Fixed `g2` gradient instead of `g1`–`g8`, missing AP badge, category badge, attach-to-story/download buttons.
- **Pagination wrong.** Prototype uses “Load more”; current uses `links()`.
- **Lightbox wrong.** Prototype is a centered modal with metadata; current is a right slide-over with only import/close.
- **Attach flow replaced by import.** Download missing.

### What to keep
`ApPhotoManager.php` query/import layer is usable; Blade view should be rewritten.

### Effort
Medium.

---

## 7. Clients — PARTIAL REBUILD

**Files:** `app-data/clients.html` vs `resources/views/livewire/admin/clients-manager.blade.php` + `app/Livewire/Admin/ClientsManager.php`

### Gaps
- **Bulk selection bar exists but is not wired.**
- **Usage/quota progress bars missing** in list.
- **Renewal dates show `created_at`** instead of real renewal.
- **Tier badges and add-ons missing** in list.
- **Export CSV non-functional.**
- **Drawer Overview lacks contacts section.**
- **Channels tab not editable.** Prototype supports email recipient add/remove, FTP test connection, API key reveal/regenerate, webhook URL; current renders raw JSON.
- **Package change + add-ons missing** in drawer.
- **Activity timeline is hardcoded “coming soon.”**
- **Pause modal incomplete.** Missing reason select, internal note, auto-resume date.
- **Deactivate modal wrong.** Uses text confirmation instead of explicit checkbox.
- **Onboard wizard is single-step.** Prototype has 3-step flow, add-ons, channel selection, review pane.

### Effort
Medium-large.

---

## 8. Packages — PARTIAL REBUILD

**Files:** `app-data/packages.html` vs `resources/views/livewire/admin/packages-manager.blade.php` + `app/Livewire/Admin/PackagesManager.php`

### Gaps
- **Add-ons section entirely missing.**
- **Feature checklist replaced by raw JSON textarea.** Prototype has toggles for wire access, photo quota, video, exclusive, API, support.
- **No color/gradient picker** for package cards.
- **No live client-view preview panel** in editor.
- **No duplicate package action.**
- **Archive-with-reassign flow missing.** Prototype warns and lets you move clients; current only toggles status.
- **No draft vs live save states.**

### Note
The JSON `entitlement_filter` approach is architecturally correct per DEC-007, but the UI is not faithful to the prototype.

### Effort
Medium.

---

## 9. Roles & access — PARTIAL REBUILD / POLISH

**Files:** `app-data/roles.html` vs `resources/views/livewire/admin/roles-manager.blade.php` + `app/Livewire/Admin/RolesManager.php`

### Gaps
- Duplicate role action missing.
- Role color picker missing.
- Quick permission presets missing (`View only`, `Uploader`, `Editor`, `Full access`, `Clear all`).
- Members section missing inside role editor drawer.
- Per-module “All / None” toggle missing.
- People list actions missing: resend invite, reactivate, deactivate.
- Role dropdown in People list is read-only.
- Module labels differ (cosmetic).

### What works
Core CRUD, permission matrix, invite modal, delete guard, audit log are functional.

### Effort
Small-to-medium polish.

---

## 10. AI settings — PARTIAL REBUILD

**Files:** `app-data/ai-settings.html` vs `resources/views/livewire/admin/ai-settings.blade.php` + `app/Livewire/Admin/AiSettings.php`

### Gaps
- **UNB Photos pre-edit toggle missing.**
- **Auto-publish confirmation modal missing.** Current toggles immediately.
- **Token usage bar / current usage / cost display missing.**
- **Per-desk token usage table missing.**
- **Status banner at top missing.**
- **Reset to defaults missing.**
- Default allowlisted categories differ from prototype.

### What works
Per-desk toggles, auto-publish, token cap, model select, house style prompt, kill switch persistence.

### Effort
Small-to-medium.

---

## 11. Story view — PARTIAL REBUILD

**Files:** `app-data/story.html` vs `resources/views/admin/story.blade.php` + `resources/views/livewire/admin/story-view.blade.php` + `app/Livewire/Admin/StoryView.php`

### Gaps
- Missing public-article layout: masthead, article + sidebar grid, featured image/caption, tags, related articles, latest-news rail, ad box, footer.
- Missing download actions: Word, Text, Image, XML, Print.
- Missing share buttons and view count.
- Missing Dhaka clock and brand chrome.

### What works
Headline, sub-head, category, dateline, body HTML, breaking badge, back link, edit button render.

### Effort
Medium. Likely needs a separate public story layout.

---

## 12. Distribution log — PARTIAL REBUILD

**Files:** none vs `resources/views/livewire/admin/distribution-log.blade.php` + `app/Livewire/Admin/DistributionLog.php`

### Gaps
- No prototype exists, but the admin log is functional yet basic.
- Missing retry UI, client-level filtering, export, and real-time badge updates described in NFR §6.

### Effort
Small-to-medium.

---

## 13. Client portal — PARTIAL REBUILD

**Files:** `app-data/client-portal.html` vs `portal/` (Next.js)

### What exists
Next.js 16 + Tailwind v4 shell, brand strip, header, wire feed list, individual story page, ISR, Meilisearch tenant-token helper, backend API routes for feed/story/search-token.

### Gaps
- Collapsible left rail (subscription tier, quota bars, filters, saved searches, recent downloads).
- Feed tabs, omnisearch with scope selector, category chips, new-story pill.
- Bulk selection bar.
- Story media/exclusive/locked/breaking badges, inline photo strip/lightbox.
- Media packs, media library grid/table/density views, asset detail modal.
- UNB Photos showcase, trending list, download actions, entitlement indicators.

### Effort
Large. This is the highest-traffic consumer surface and should run in parallel with admin work.

---

## 14. Field apps — NOT BUILT

**Files:** `app-data/mojo-field-desk.html`, `app-data/photo-field-desk.html`

### Status
No PWA, static implementation, service worker, manifest, or routes exist. Per AGENTS.md / DEC-008, field apps are deferred and out of current scope.

### Effort
Large, separate track. Do not start unless scope changes.

---

## 15. Shared admin chrome — POLISH

**Files:** `app-data/index.html` chrome vs `resources/views/layouts/admin.blade.php` + `components/sidebar.blade.php` + `components/topnav.blade.php`

### Gaps
- **Client portal icon** in topnav is `href="#"`.
- **Content pipeline** sidebar item is missing/replaced by Add News.
- A few other `#` links remain in sidebar/topnav.

### What works
Brand strip, sidebar sections, sticky topnav, search shortcut, live clock, notifications dropdown, user dropdown are functional.

### Effort
Small sweep.

---

## 16. Dashboard — PARTIAL REBUILD

**Files:** `app-data/index.html` body vs `resources/views/admin/dashboard.blade.php`

### Gaps
- KPI deltas are static/generic; prototype has realistic `+3 from yesterday`, `-0.3% from last week`.
- Success-rate delta icon/color wrong.
- Recent stories are hard-coded; `View all` is `#`.
- Top clients are hard-coded; `All clients` is `#`.
- Distribution bars are decorative (always full).
- Missing download FAB.
- Content pipeline sidebar item missing.

### What works
Layout, navigation, KPI headline numbers, and notifications are functional.

### Effort
Small-to-medium.

---

## Recommended priority order

1. **Shared chrome sweep** — fix all `href="#"` links so navigation does not regress again.
2. **Add News** — full rebuild in slices (editor, media, organize, review, AI, workflow).
3. **UNB Photo Manager** — full rebuild; it is the other heavy admin surface and blocks field-intake workflow.
4. **English News list** — partial rebuild for workflow drawer/takeover/notes, which Add News review step depends on.
5. **AP Photo Manager, Clients, Packages, AI settings, Roles** — partial rebuilds in parallel.
6. **English Service / Delivery settings / Story view** — rebuild when distribution/client-portal work begins.
7. **Client portal** — parallel track, not blocked by admin.
8. **Field apps** — deferred per DEC-008.

---

## Quality gate

No segment should be marked done until:
- It matches the prototype side-by-side at 1440px and 1920px.
- Every interaction listed in `app-data/README.md` §5 for that page works.
- Playwright spec covers each interaction.
- `php artisan test` passes and `npx playwright test` passes for that segment.
- Live smoke is recorded per `docs/workflow/live-test-runbook.md`.
