# Prototype → App Fidelity Audit v2

**Date:** 2026-08-29 — second pass after wizard hotfixes (publish chain, `is_internal`, `role->code`, preview `wire:ignore`, Alpine fix)
**Method:** 7 parallel agents compared every `app-data/*.html` vs current `resources/views/**` + `app/Livewire/**` + `resources/js/wizard/**` + `portal/**`. No code was changed during the audit.
**Prior audit:** `docs/plans/prototype-fidelity-audit.md` (still valid for context). This is the delta.

---

## 0. TL;DR

| Segment | Prototype | Implementation | Verdict v2 | Delta since v1 |
|---|---|---|---|---|
| **Add News wizard** | `add-news.html` | `livewire/admin/add-news.blade.php` + `AddNews.php` + `wizard-main.js` | **Partial — now ~70% faithful** | ⬆ **major fix:** stepper labels, publish chain, preview wrapper, doc-import now faithful; AI drawer/gate still scaffold, media rights not persisted, shift missing |
| **Dashboard** | `index.html` | `admin/dashboard.blade.php` + `routes/web.php` `/admin` | **Scaffold — dead variables** | → no change |
| **English News list** | `english-news.html` | `livewire/admin/news-list.blade.php` + `NewsList.php` | **Scaffold — 4 of 7 columns missing, drawer read-only** | → no change |
| **UNB Photo Manager** | `unb-photo-manager.html` | `livewire/admin/photo-manager.blade.php` + `PhotoManager.php` | **Scaffold — upload bypasses review, no justified grid, no ZIP, no field-intake modal** | → no change |
| **AP Photo Manager** | `ap-photo-manager.html` | `livewire/admin/ap-photo-manager.blade.php` + `ApPhotoManager.php` | **Scaffold — minimal ingestion queue** | → no change |
| **Clients** | `clients.html` | `livewire/admin/clients-manager.blade.php` + `ClientsManager.php` | **Scaffold — no bulk, no usage, no channels CRUD** | → no change |
| **Packages** | `packages.html` | `livewire/admin/packages-manager.blade.php` + `PackagesManager.php` | **Scaffold — no add-ons, no checklist, no reassign** | → no change |
| **Roles** | `roles.html` | `livewire/admin/roles-manager.blade.php` + `RolesManager.php` | **Scaffold — no duplicate/colour/presets/members/people actions** | → no change (system-role lock still blocks view) |
| **AI Settings** | `ai-settings.html` | `livewire/admin/ai-settings.blade.php` + `AiSettings.php` | **Scaffold — missing photos toggle, usage bar, banner, reset** | → no change |
| **Story view** | `story.html` | `admin/story.blade.php` + `StoryView.php` | **Partial — admin reader only** | → no change |
| **English Service** | `english-service.html` | `livewire/admin/service-config.blade.php` + `ServiceConfig.php` | **Full rebuild — wrong surface** | → no change |
| **Delivery Settings** | `delivery-settings.html` | `livewire/admin/delivery-settings.blade.php` + `DeliverySettings.php` | **Full rebuild — wrong surface** | → no change |
| **Distribution Log** | *(none)* | `livewire/admin/distribution-log.blade.php` | **Partial — different entity than prototype history** | → no change |
| **Client Portal** | `client-portal.html` | `portal/app/**` (Next.js ISR) | **Partial — shell only** | → no change |

**Only Add News moved.** Everything else remains exactly as flagged in v1 and in `remediation-fix-all-v2.md`.

---

## 1. Add News — what the hotfix actually fixed

### Faithful now (passes Playwright 11/11)

| Area | Evidence |
|---|---|
| **Stepper** | `Write → Media → Organize & access → Review & publish` labels + `.stp.done` green check + `stp-badge` live count + `add-news-wizard.css` byte-faithful |
| **Tags autocomplete** | `SAVED_TAGS` 16 entries, `#` suggest + keyboard nav + `commitTag` that calls `addTag()` + `syncPreview()` — 1:1 |
| **Doc import** | `docOverlay` modal + mammoth `.docx`/`.txt` parse + preview `headline=lines[0]` + `syncFromDoc()` + toast — faithful |
| **Publish chain** | `AddNews::publish()` walks `draft → in_review → approved → published` so every `story_events` row is audited; `quickPublish` reuses it. Success card now only renders after server `successState='published'` — fake DOM removed (§7 anti-pattern). |
| **Preview wrapper** | `wire:ignore` on `#editorWrap` and `#pvBody` + `syncPreview()` that writes `innerHTML` — now compliant with `AGENTS.md §10` |
| **Collapse + device overlay** | `pv-collapsed` + `pvStrip` + `pvOverlay` `dev-desktop/tablet/mobile` max-width toggles + `localStorage unb_pv_collapsed` — faithful |

### Still partial / scaffold

| Area | Gap |
|---|---|
| **Live preview sync** | `syncPreview()` only listens to `headline/subhead/brief/cat/author` + `quill text-change`. Missing `MutationObserver(tagBox)`, `featuredPreview` class, `attachGrid` children — adding a tag via `removeTag` (Livewire re-render) or picking featured media does not re-trigger unless explicitly called. No listener for server `story-updated` dispatch. |
| **Workflow strip** | Pill/owner/take-over/send wired, but `wf-shift` ("shift ends 6:00 PM") missing — no `$shiftEnd` prop. `approved` pill mapped to `live` alias correctly, but `killed/archived` pills absent. |
| **Media** | Visuals faithfully ported (1200×630 `has-photo`, `g1-g8`, `attach-tile` + `bulk-bar`, `drop-overlay`, `up-toasts`). Function is scaffold: `fileInput` has no MIME/size check, `MediaService` not called, `rights` select not persisted (`story_media` insert hardcodes `role='inline'`), `editOverlay` crop not wired, bulk rights only in-memory. Archive picker supports single (featured) + multi (attach) but uses gradient placeholders, no portfolio images, no `00:48` video badge. |
| **AI drawer / gate / compare** | DOM for `aiDrawer` + `aiGateOverlay` + `cmpOverlay` landed, but `wizard-main.js` still has local heuristics (`aiSuggestBtn` lead extraction, `aiNamesBtn` Levenshtein) — not consuming `AiService` `ai-pack-ready` dispatch. `ai-touched` amber markers are faithful (Livewire toggles class), but gate never intercepts `publishBtn` (server `publish()` does not check `aiTouched` before walking the chain). `cmpOverlay` word LCS diff not wired. |
| **Shift/ownership nuance** | Notes thread correctly splits `editor` (navy left) vs `sub` (green left) via `role.name`, but `sys` dashed italic styling is rendered but never produced by server except `take_over`. |
| **Dual state** | `showStep(n)` toggles `.active/.done` in DOM **and** calls `wireComponent.set('step', n)`. `go()` validates `headline/brief` then `autosave()`. Both own `step` — works but redundant and racy under `--workers=2`. |

**Verdict:** POR far ~70% faithful — the red quality gates in `AGENTS.md §10` / `docs/workflow.md §7` would have caught the pre-fix fake-success and now pass for this surface.

---

## 2. Dashboard — scaffold, dead variables

**Route** `routes/web.php:10-21` now queries `$publishedToday`, `$activeClients`, `$successRate`, `$recentStories`, `$topClients` live — **but** `admin/dashboard.blade.php:73-157` ignores `$recentStories`/`$topClients` entirely and renders **4 + 3 hardcoded static rows** copied from `index.html`.

- **KPI deltas critically wrong:** prototype has `+3 from yesterday / +2 this week / -0.3% from last week (red down) / +5 from yesterday`. Impl renders `live today / total active / deliveries (green activity icon, never red) / +5` — only card 4 matches. `routes/web.php` computes no `yesterday/this-week` deltas; `$exclusiveToday` double-counts `$publishedToday` (no `is_exclusive` column — should be something else).
- **Recent stories** `View all → #` (should be `route('admin.news','en')`), hard-coded headlines (`Nandini killing…`), word counts (`342`), tags, status pills. No loop, no empty state.
- **Top clients** `All clients → #` (should be `route('admin.clients')`), `withCount('clientChannels')` counts FTP channels not today's downloads; no `orderBy`, no tier badge, no `Premium/Standard/Basic` logic, no `12/6/3` dynamic.
- **Distribution bars** tooth static `34×6px green gradient` — no binding to delivered/total.
- **FAB** (`fab` 50px navy circle, `title Download`) deleted — 0 hits in `resources/views/**`.
- **Content pipeline** nav (`Content pipeline` + red `4`) missing — replaced by `Add News` in `components/sidebar.blade.php:14`; no route, no badge (`nav-badge`), breaks `FR-NWS-018`.
- **6 `href="#"` remain** on the page/chrome: dashboard `View all`/`All clients` + sidebar `MoJo`/`Photo desk` + topnav globe `Client portal` + Preferences.

**Fix:** delta queries + red/green pill, `@forelse $recentStories` / `$topClients` loops with avatar initials, dynamic `dist-bar` width + tooltip, FAB restore in `layouts/admin.blade.php`, Content pipeline nav + badge + `route('admin.news','en', ['status'=>'in_review'])`, sweep `#`.

---

## 3. English News list — scaffold, 4 of 7 columns missing

**Prototype** `english-news.html:241-1131` is a `<table>` desk (7 cols, Switch for Live/Draft, bulk checkbox, thumb `t-blue/green/purple` 56×40, title, category-colour pill, subcat, views eye, status pill, actions Edit/Delete, owner-line `wf-ava` + note-badge `wfd-open`, pagination `page-btn` + `Showing 1–8 of 93,672`).

**Current** `news-list.blade.php:10-46` is a `grid-cols-[1fr_140px_150px_90px]` div grid `Story | Category | Status | Owner`, pill filter `All/Draft/In review/Published` (no `Live` / `Needs work` / `Approved` / `Archived`), live search only on `headline like`, generic `links()` paginator.

| Missing | Impact |
|---|---|
| `checkbox` bulk, `thumb` 56×40, `cat-tag` colour (`bangladesh` blue / `sports` green / `world` purple), `subcat` col, `views` col, `switch` Live/Draft toggle | Visual scan + bulk workflow gone |
| `status pill` colours wrong (`in_review` amber vs blue `wf-pill review`), `Live` switch gone, label `Needs work` → `Changes requested` mismatch | Status semantics wrong |
| Per-row `Edit`/`Delete` `icon-btn` | No row CTA |
| `owner-line` `wf-ava 19px + Maria Mimi · with editor Shohel…` under title | Separate `Owner` column plain name only |
| `note-badge` `2` message bubble + `wfd-open` under status | Badge inline after brief, not interactive, wrong bg |
| **Workflow drawer** `.wfd 445px slide` with pill flow `Draft done → In review now → Approved → Published` + `wfd-arrow`, owner card `avatar 30px + shift until 6PM + Take over btn + handover hint`, threaded `nt-item editor/sub/sys` + `nt-role` + `nt-time` Dhaka + `nt-reply` composer | Drawer is `560px paper` read-only: `events` log rows + flat `notes` list + extra `Story body` card that prototype never shows; no stepper, no avatar/shift, no `Take over`, no role badges, no composer |
| **Takeover** `take_over` handover + `sys` note + `story_events` + `locked_by/locked_at` + 409 | Entirely absent — no `takeOver()` method, no button, no 409 |
| **Notes composer** `nt-reply textarea + Send` | Missing — read-only thread |
| **Pagination** `‹ 1 2… 11708 11709 ›` + `Showing 1–8 of 93,672 · Page 1 of 11,709` | Generic `links()` 15-per-page, no `results-info` |
| `Export` button | Missing |

---

## 4. UNB Photo Manager — scaffold, upload bypasses review

**Prototype** `unb-photo-manager.html:172-1107` is a **justified flex grid** (`dam-grid` height 172px, `flex-grow: calc(var(--r)*100)`, `--r` from real image), 7 workflow tabs (`All assets`, `Field intake` 📥, `Needs review` `review`, `In library`, `Packaged`, `Published`, `Embargoed`) with `.n.alert` crimson, hover-reveal `dam-check` + gradient `dam-overlay` caption, status pill `9px caps` with `STATUS_META` colours, burst stacks (`stack 9`, `Frame N set as stack cover`), inspector sticky `1fr 350px` with editable caption/photographer/location/keywords/package/attached story/client usage + `Save changes` / `Approve`, bulk bar (`Approve`, `Assign package`, **JSZip** `unb-photos-N.zip` + `captions.txt`), field intake **batch queue** grouped by `event` with avatar/wait/urgency (`breaking` crimson) + per-photo `ok/no` + per-batch `Approve all / Request re-edit… / Reject batch…` + **reason modal** 460px (4 `REJECT_REASONS` vs 4 `REEDIT_REASONS` + note), drag overlay `z-index:200` blur + `dragDepth` counter, toolbar filters (`damSearch` in cap/by/loc/kw + photographer + category + sort + unattached).

**Current** `photo-manager.blade.php:1-116` + `PhotoManager.php:17-92`:
- 3 pills only (`Library`, `Field intake`, `Re-edit`) — missing `All`, `Needs review`, `Packaged`, `Published`, `Embargoed`; field query `whereNotNull(batch_id)` not `status=field`; counts wrong.
- Fixed `grid grid-cols-6` `aspect-[4/3]` — destroys aspect preservation.
- Check always visible, overlay caption always in `p-2`, not hover gradient.
- Pill shows `kind` not status, no colours.
- No stacks at all.
- Inspector is a `fixed inset-0` slide-over `420px` read-only (title/caption/category/size/credit/captured) — no editable inputs, no story link, no usage box.
- Bulk bar only `Approve`+`Clear` — no `Assign package` select, no `ZIP`.
- Field queue `MediaBatch::with assets` but no `fq-pacts` reject, no footer `Approve all/Re-edit/Reject`, no modal (`fq-overlay`) or reason radios — `reject()`/`requestReedit()` methods don't exist; upload via `wire:model uploads` creates `status=library` (bypasses `review`), not `status=review`.
- Drag overlay exists but `@drop.prevent="dragOver=false"` never calls upload, no `dragDepth`.
- Toolbar only `search title like` — no photographer/category/sort/unattached.

---

## 5. AP Photo Manager — scaffold, minimal queue

**Prototype** `ap-photo-manager.html:172-912` has **5-filter toolbar** + **13 category chips** + `Showing X of 1,248` + **8-gradient cards** (`g1-g8`) with `AP` badge top-left, `ap-cat` coloured pill, `ap-expand` hover, `Attach to story` → `.done` green + `Download original` per card, centred **880px lightbox** with 5 rows (Credit/Category/Wire date/Dimensions/Downloads) + `Load more (5)` incremental reveal + `Sync log`/`Sync now` + `.sync-note`.

**Current** `ap-photo-manager.blade.php:1-34` + `ApPhotoManager.php:16-27`: 34-line ingestion queue (`source=ap`, `status field/library`), `search wire:model.live` on `title like` only, `status select All/Incoming/Imported`, `grid-cols-6` `g2` single gradient for every card, `AP · status` white pill centred, per-card `Import` on hover only, **no** chips, **no** `All sub categories / Asia…`, **no** tag filter, **no** `Sync` buttons/note, `Showing` count, `g1-g8` variety, `ap-cat` pill, `ap-expand`, `Download`, `done` state, centred **lightbox** (drawer `420px` with only `Import to library + Close`), no Credit/Dimensions/Downloads rows, no `Load more`, pagination `links()` instead.

---

## 6. Clients — scaffold, no bulk/quota/channels

**Prototype** `clients.html:400-1325` has **5-stat strip** (Total / Active / Paused / **Renewals ≤45d** / **Delivery issues fail**), toolbar search `name+city+type+contacts` + 4 status chips + tier + 3-sort (Name/Renewal/**Usage**), sticky **navy bulk bar** with `Pause selected` + `Change package…` + `Export selected`, list `checkbox + logo g1-g8 + channel icons 4× (portal/email/ftp/api `on` green / `fail` crimson) + `cl-tier` pill (Premium amber / Standard blue / Basic grey) + `cl-usage` `dl/quota` + `u-bar/u-fill width pct% (crimson >85)`, drawer `500px 4 tabs` (Overview contacts/entitlements/usage-grid `4 boxes` + note `textarea`, Channels CRUD with switches/m- chips/FTP inputs/Test/Api Reveal+Regenerate/webhook, Package 3 radios + 2 add-ons + `Effective` + `Apply`, Activity `tl-item` timeline), modals `Pause` (Reason* + Note + Auto-resume date) / `Deactivate` (checkbox confirm) / **Onboard 3-step wizard** (`Organisation* + Type + City + Contact* + Email*` → Package radios + add-ons + channels → Review), export `unb-clients.csv` 10 cols.

**Current** `clients-manager.blade.php:1-140` + `ClientsManager.php:16-103`: 4 stats but `Renewals` hard-coded `0`, `Issues` `0`, search `name/code like` only, status/tier chips present, sort `Name/Renewal` (no `Usage`, renewal uses `created_at`), list **no checkbox**/bulk bar, logo uniform `bg-navy-800`, package name only (no `cl-tier`), no `cl-usage` bar; drawer `520px` read-only header + `Details` (Status/Type/Billing) + `Users` list, `Channels` raw `json_encode(config)`, `Package` `Since` only, `Activity — coming soon`. Modals `Pause` generic `status suspended` + `Deactivate` `Type DEACTIVATE` (vs prototype checkbox), never revokes channels. Onboard single `640px` modal `Organisation* + Type newspaper… + Email* + Package select dropdown` (no City/Contact/Package radios/add-ons/channels/Review), creates only one `api` channel hard-coded. No bulk, no usage-grid, no channel CRUD, no reassign, no CSV.

---

## 7. Packages — scaffold, no add-ons/checklist/reassign

**Prototype** `packages.html:400-912` has **4-stat strip** live (`Live packages` + `Add-ons live` + `Clients covered` + `Monthly recurring` lakh `₹` + `Add-ons` revenue), **8-gradient package cards** with persisted `grad`, status `live/draft/archived` (3 states) with `Draft` badge, **6-row feature checklist** (Wire access 3 options / Photo quota 5 options / Video / Exclusive / API / Support with check/x), **avatar stack** 4 initials, actions `Edit`+`Duplicate` (→ `name (copy)` draft empty clients)+`Archive`/`Restore`+`Delete`, full **add-on list** table (Add-on | Desc | Available with | Price | Status **clickable toggle live/draft** |) with `ao-dot` grad, tiers, `৳/mo`, edit/delete, guard; editor modal `860px ed-grid 2-col` left form `Package name* + Price* + Card colour 8 dots sel + Short desc + Wire access + Photo quota + 4 checkboxes` **right sticky `ed-prev` live preview** (`prev-card` `prev-top` gradient, feats 6 rows sync on `input`), footer `Cancel / Save as draft / Save & publish` (2 statuses); **archive-with-reassign** overlay (`N clients are on this package: list + Move clients to <select> live others`).

**Current** `packages-manager.blade.php` + `PackagesManager.php`: `active/archived` only (no `draft`/`live`), card gradient `g{idx%8+1}` not persisted, no colour picker, **feature checklist missing** — replaced by raw `json_encode(entitlement_filter)` box, avatars missing (only count), **no Duplicate**, **add-ons entirely absent** (topbar missing `New add-on`, second stat hard `0`, no `addon-list`, no model), editor `640px` single col `Code* + Kind* + Name* + Price + Status active/archived + Description + Entitlement filter JSON textarea 4 rows mono` (no colour, no wire/quota selects, no included checkboxes, no `ed-prev` preview, one `Create/Save` not draft/live), **archive-with-reassign missing** (`archive()` toggles `active↔archived`, no client list, no `<select>`, no move; `delete()` guards but offers no reassign UI).

---

## 8. Roles — scaffold, no duplicate/colour/presets/members/people actions

**Prototype** `roles.html:500-1180` has **role cards** `rc-logo gX` initials, badge `System(locked)/Client/Custom`, **6-module perm chips** `full(green)` / `view(muted)` / else blue with ratio `N/M`, **members avatar stack 4 + `+N`**, `Edit permissions` (`View permissions` for system) / `Duplicate` / `Delete` (hidden for system), **new card dashed**, colour picker `8 dots sel` in **new modal + drawer**, **preset row** `View only/Uploader/Editor/Full access/Clear all`, per-module `All↔None` toggle with `on/total` badge `danger dashed` for delete, **members section** `mx-mem` chips in drawer, **People 6-col** list `Name/Desk/Role select editable/Status` with `Resend invite/Reactivate/Deactivate`, `You` badge, `Last Online now…`, `System-role` **read-only** locked render (disabled chips 0.75).

**Current** `roles-manager.blade.php` + `RolesManager.php`: cards `g{idx%8+1}` loop index (colour not persisted), badges via `is_locked`/`type`, perm chips wrong modules **8 generic** (`stories, stories_bn, media, clients, packages, distribution, settings, ai`) `view/create/edit/publish/delete`, class only `green if publish/delete` (no `full/view` nuance, no ratio), no `No module access` fallback; **no Duplicate**, colour picker **nowhere**, presets **missing**, per-module button **always** `Allow all` (no `None` toggle), **no members in drawer**, **People** table 5-col read-only (`Name/Desk/Role` static text, no `<select>`, no `You/Last/Actions`, no `Resend/Deactivate`), **System role** `openEdit` immediately toasts and returns — **no read-only view** at all.

---

## 9. AI Settings — scaffold, missing guardrails visualisation

**Prototype** `ai-settings.html:700-1150` has **3 desk switches** purple `English+Bangla checked + UNB Photos unchecked`, **auto-publish double-confirm** `autoModal` `Enable AI auto-publish?` + `Keep it off/Yes`, `Token usage & budget` **gradient bar** `usageFill min(100, tokens/cap)` purple + `usage-num 312,400 of 500,000 · ৳4,180` + **3-row desk table** (English 418/188,200 … Photos 89/26,600) + cap `input 500k min50k step50k` + hint, **status banner** top purple `AI pre-edit is active + Auto-publish: OFF` (+ killed `off grey All features disabled`), **allowlist 7 chips** `Weather, Sports results, Market close, Currency rates ✓` + Bangladesh/World/Business ✕ hint `Never allowlist politics`, `House style prompt` card full UNB rules, sticky `Save bar` `Reset + Save` → `localStorage`.

**Current** `ai-settings.blade.php` + `AiSettings.php`: 2 switches `preeditEn/Bn` (green/amber, not purple) — **Photos desk toggle missing**, `autoPublish` instant toggle (no `autoModal`), 2-card layout: `Budget & Model` cap input + extra `Model select openai:gpt-4o…` (not in prototype) **no usage-bar**, **no desk-table**, cost `৳4,180` calc, only `Current JSON` dump, no status banner (replaced by `KILL SWITCH ON` crimson header), **no Reset** button, allowlist **4 checkboxes** `Bangladesh, World, Sports, Business` (wrong set — includes the two prototype explicitly warns never to allowlist + missing Weather/Market close etc.), boxes `warn/danger` hint absent, house prompt inside `Budget` card 4 rows placeholder, no `Save bar` sticky, persistence `Setting ai.desk` (not `localStorage`).

---

## 10. Story view + Service + Delivery + Portal — partial / wrong-surface

| Prototype | Impl | Verdict |
|---|---|---|
| `story.html` 484L **public article** masthead `U 36px + UNB English Service serif`, Dhaka live-dot clock `Intl Asia/Dhaka`, `article-grid 1fr 330px`, `byline 13px border UNB News + views 12,418 + share FB/X/copy → clipboard green flash`, `story-body 16.5px drop-cap 54px crimson + pull-quote 21px navy left 3.5px + signoff END/UNB/…`, `5 actions` Word/Text/Image 1200×630/canvas + XML + Print + Blob, `featured 16/8.6 g1`, 4 tags, 3 related `card-row hover -3px`, 5-item `rail sticky 76px + ad dashed`, navy `footer gradient` | `admin/story.blade.php` 4L wrapper + `livewire/admin/story-view 13L` `where public_id` (`max-w-[780px]` single col, `Back to news`, `BREAKING pill`, `category·date·status`, `dateline·word_count`, `brief paper`, `prose body_html`, `Edit`) | **Partial — admin reader only** — mashhead/grid/downloads/share/featured/tags/related/rail/footer all missing |
| `english-service.html` 540L **public homepage** `1240px` masthead `44px U + UNB English Service + clock/ticker pulse 5s + catnav 20 links sticky + home-grid 1fr 330px hero overlay 16/9.2 g8 + 3×4 card-row hover + rail tabs Latest/Popular 5-thumb | `livewire/admin/service-config 10L` `/admin/service/{service}` `Setting service.en` `Wire name + Description + Enabled` | **Full rebuild — wrong surface** (public wire home vs admin 3-field form) |
| `delivery-settings.html` 607L **client portal** zero-touch FTP/SFTP master switch + status `Connected sftp://ftp.dailystar.com pulse` + `Test/Edit creds host/port/user/auth` + channel switches per wire/photo + format `NewsML/NITF/JSON/RSS` + schedule + API `https://api.unbnews.org/v1 copy + masked key Reveal/Regenerate double-confirm + webhook` + Alerts 4 email chips + switches + History `lic-pill unb(green)/ap(blue) + Asset/License/Downloaded by/Date + CSV + audit note` | `livewire/admin/delivery-settings 13L` `/admin/delivery-settings` `Setting delivery {retryAttempts,backoff,autoPause,atLeastOnce}` 3 number inputs + checkbox | **Full rebuild — wrong surface** (client delivery UX vs admin backoff tunables — different entity) |
| `distribution-log` *(no prototype)* | `livewire/admin/distribution-log 30L` `Delivery with client,channel paginate 20, search payload_hash, status filter, Total/Delivered/Failed stats, retry queued` | **Partial — different entity** than prototype history (client ledger vs admin ledger; no `payload_hash` column in table, no `next_attempt`, no export) |
| `client-portal.html` 1900L+JS 258px rail **3 tabs** News wire/Media library/UNB Photos, omnisearch `Everywhere/Headlines/Tags/Captions + kbd /`, cat chips All→Environment, bulk bars (Word/XML/CSV + ZIP `jszip`), badges Exclusive amber/Video purple/Locked dash/Exclusive-to-other, photo strip 132px + `caps-box` searchable highlights + lightbox `850px blur + lb-nav`, `pack-grid` 2-col, `ml-grid` 215px + density cozy/compact, src chips, filters Date/Category/Variant, `ml-count`, `ph-hero 1.9fr 1fr`, `gal-row`, `coll-row`, `trend-list`, `am-modal` 880px | `portal/app/page 87L` **shell only** `fetch /api/v1/portal/feed ISR 60` list + single `input placeholder Search…` + `lib/search tenantToken race 5s` orphan | **Partial — shell only** : no rail quotas/filters/saved/recent, no tabs, no scope search, no bulk/ZIP, no strip/lightbox, no packs/library density, no Photos hero/collections |

---

## 11. Overall Verdict

Only the **Add News wizard** materialised after the hotfix. Everything flagged in `remediation-fix-all-v2.md` §2 (News list, Photo DAM, clients, packages, roles, AI, story/service/delivery/portal) **remains scaffold** exactly as in v1. The `docs/knowledge-inventory` is still in sync only for Add News; the other surfaces await their per-surface faithful rebuild PRs — one PR per row of the file map `app-data/README.md §1` as demanded.

**No PR should flip any of the other rows to ✅ until `app-data/README §17` (side-by-side at 1440/1920, every §5 interaction, FR acceptance, Bangla NFC, no demo affordances) + `AGENTS.md §10` (parity + live smoke + Playwright DB assertion) + `docs/workflow.md §7–§8` (no fake success, wire:ignore, no double Alpine) are green for that surface.**

