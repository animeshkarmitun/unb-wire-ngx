# FR Cross-Check Report — `app-data/v1-functional-requirements.md` vs Implementation

**Task:** M13-QA-001 (COS-28) · **Date:** 2026-09-23 · **Method:** per-FR audit of code + tests against `app-data/v1-functional-requirements.md`.

**Rubric:** `done` = implemented + tested + matches spec · `partial` = some parts missing or untested · `missing` = not implemented · `changed` = implemented but deviates from spec.

**Legend:** [M] must-have per spec · [S] should · [C] could.

---

## Summary

| Family | done | partial | missing | changed | Total |
|--------|------|---------|---------|---------|-------|
| FR-NWS (authoring/workflow/surfaces) | 11 | 8 | 0 | 0 | 19* |
| FR-AI (AI desk) | 1 | 6 | 2 | 1† | 10 |
| FR-MED (media + AP) | 0 | 8 | 2 | 7 | 17 |
| FR-FLD (field apps) | — | — | — | — | 10 (deferred, DEC-008) |
| FR-DST (distribution) | 3 | 5 | 1 | 0 | 9 |
| FR-CLT (clients) | 2 | 2 | 0 | 0 | 4 |
| FR-PRT (portal) | 5 | 1 | 1 | 1 | 8 |
| FR-ACC (access/settings) | 3 | 2 | 0 | 0 | 5 |
| FR-NTF (notifications/audit) | 0 | 3 | 0 | 0 | 3 |
| **Total (excl. FLD)** | **25** | **35** | **6** | **9** | **75** |

\* FR-NWS-001…019 + FR-NWS-020. † counted in missing/changed sets below where noted.

**FR-FLD-001…010:** all **deferred** per `DEC-008` (field PWAs + `Modules/Field` services); `assignments`/`devices` tables retained for schema parity. Not counted as gaps.

**FR-SEC:** the spec has no `FR-SEC-*` items (security lives in NFR §8). Tracked via the dedicated security audit (**COS-27**).

---

## 1. FR-NWS — Story authoring, workflow, surfaces

| FR | Title | Status | Evidence | Tests | Deviations |
|----|-------|--------|----------|-------|------------|
| FR-NWS-001 [M] | 4-step story wizard | done | `AddNews.php` (`step`, `next`/`go`/`prev`, `validateStep`), `partials/add-news-stepper.blade.php` | `AddNewsTest` stepper suite (5 tests) | — |
| FR-NWS-002 [M] | Draft autosave & restore | partial | `AddNews::autosave`, `#draftRestore` banner in `add-news-step1-write.blade.php` | `AddNewsTest::test_autosave_*` (2) | Restore/Discard banner flow untested; draft = story row + wizard-local state, not the prototype `localStorage('unb_draft_v1')` (documented in FR) |
| FR-NWS-003 [M] | Wire editor toolbar | partial | `#editorToolbar` in `add-news-step1-write.blade.php` (dateline/pullquote/table/signoff/cleanup), `resources/js/wizard/wizard-main.js` handlers, History modal (M10-HIST-008) | Toolbar JS untested; revision history covered by `RevisionRestoreTest`, `StoryViewTest::test_compare_versions_shows_diff` | Find-&-replace is the wizard find bar; toolbar effects are client-side only |
| FR-NWS-020 [S] | Import from document | done | `AddNews::syncFromDoc` (+ auto-brief truncation ≤280) | `AddNewsTest::test_sync_from_doc_*` (2) | — |
| FR-NWS-004 [S] | Live preview | partial | `syncPreview()` + `wire:ignore` wrapper (see `docs/workflow.md` §8), step4 preview | none (UI-only) | Uncovered by automated tests |
| FR-NWS-005 [M] | Brief & headline constraints | done | `validateStep`: `headline required\|max:300`, `brief required\|max:280`; publish requires `categoryId` | `AddNewsTest::test_step1_validation_*` | — |
| FR-NWS-006 [M] | Media attachment | partial | `setFeatured`/`toggleMedia`/`clearMedia` → `story_media` (caption_override) | `AddNewsTest` media suite (3) | "Add media after publish fans out" broken — no update fan-out (see FR-DST-008) |
| FR-NWS-007 [M] | Organization & access | partial | category/subcategory/tags/language/priority/`embargoUntil` in `AddNews` (explicit-tz `parseEmbargo` → UTC), `TriggerMatcher` embargoed trigger | `LivewireTest` (embargo tz), `TriggerMatcherTest` | Distribution preview ("which packages will receive it") unverified in step 3 |
| FR-NWS-008 [M] | Publish & quick publish | done | `publish`/`quickPublish` walk draft→in_review→approved→published; `published_at` set once | `AddNewsTest` publish suite (6), `StoryWorkflowTest` | — |
| FR-NWS-009 [M] | Bangla desk mirrors English | partial | `setLanguage('bn')`, `mirror_of_id` schema + model | `AddNewsTest::test_language_switch_*`, `SeedWorkflowTest::test_bn_story_*` | Admin UI text/typography is English-only; Bangla pages not mirrored |
| FR-NWS-010 [M] | Workflow states | done | `StoryService::TRANSITIONS` + per-transition RBAC + `story_events` audit | `StoryWorkflowTest` (valid/invalid transitions) | No `scheduled` state — embargo-as-attribute per DEC-007 (documented deviation) |
| FR-NWS-011 [M] | Send to editor | done | `AddNews::sendToReview` → `in_review` + `notifyReviewRequested` | `AddNewsTest::test_send_to_review_*`, `EditorialNotificationTest` | — |
| FR-NWS-012 [M] | Internal notes thread | done | `NoteService` (immutable, `is_internal`), system events inline | `AddNewsTest` note suite, `EditorialNotificationTest`, `AuditLogWiringTest` | — |
| FR-NWS-013 [M] | Ownership & shift handover | done | `StoryService::takeOver` (lock force-release, custody note + `handover` event + audit) | `AddNewsTest::test_take_over_*`, `StoryWorkflowTest` | — |
| FR-NWS-014 [M] | Concurrent editing protection | done | `locked_by`/`locked_at` soft lock + optimistic `version` → 409 `ConflictHttpException` | `StoryWorkflowTest::test_stale_version_returns_409`, `test_transition_version_bump_makes_stale_edit_409` | — |
| FR-NWS-015 [M] | Workflow visibility in lists | done | `NewsList` status pills/owner/notes badge + workflow drawer (status flow, take over, notes) | `NewsListTest` | — |
| FR-NWS-016 [M] | Live toggle / unpublish | partial | Kill via workflow (`killed` transition, audited, fan-out dispatched) | `StoryWorkflowTest` kill path | No Live-toggle-off control in the list; no true unpublish state; kill notices never reach clients (FR-DST-008) |
| FR-NWS-017 [M] | Dashboard | done | `DashboardService` 4 KPIs + deltas + recent stories + top clients, deep links | `DashboardTest` (3, incl. N+1 guard) | Alert row (field intake/failed deliveries/AI budget) only partially surfaced |
| FR-NWS-018 [M] | Content pipeline | partial | Sidebar "Content pipeline" link + `in_review` badge → `admin.news?status=in_review` (`sidebar.blade.php:15`) | none | No kanban grouped-by-status pipeline view (spec note: "page pending design"); interim filtered list only |
| FR-NWS-019 [S] | Story detail view (admin) | done | `StoryView` (reader chrome, timeline, versions diff/restore, notes) | `StoryViewTest` (8), `RevisionRestoreTest` (7) | — |

---

## 2. FR-AI — AI desk

| FR | Title | Status | Evidence | Tests | Deviations |
|----|-------|--------|----------|-------|------------|
| FR-AI-001 [M] | Start with AI (raw → draft) | partial | `AddNews::callAi('generate')`, `OpenAiProvider`/`StubAiProvider` (`config/services.php` `OPENAI_DRIVER` default **`stub`**) | `AiServiceTest`, `AddNewsTest::test_apply_ai_sets_touched_flags` | Pipeline stubbed by default (OpenAI path unproven); BN→EN translate inverted (does EN→BN); no `en_tags`/new-facts in pack |
| FR-AI-002 [M] | Pre-edit suggestions drawer | partial | `partials/add-news-ai-drawer.blade.php` (per-card apply) | `AddNewsTest` apply-ai tests | Stubbed; single headline (no variants/rationale); static "Tokens audited" footer |
| FR-AI-003 [S] | Raw vs AI compare | changed | `#cmpOverlay` in `add-news-modals.blade.php` | none | Inert DOM: no opener, no word-level diff, `#cmpApply` unwired (see `docs/plans/prototype-fidelity-audit-v2.md`) |
| FR-AI-004 [M] | AI-touched markers | done | `aiTouched` flags + `✦ AI — unreviewed` labels, cleared on human edit | `AddNewsTest` (4 marker tests) | — |
| FR-AI-005 [M] | New-facts warning | missing | `AiService` always `'new_facts' => null`; `#gateFacts` hardcoded "0 facts flagged" | none | No fact extraction/guard at all |
| FR-AI-006 [M] | Publish gate for AI-assisted stories | partial | `StoryService::transition` gate (autoPublish+allowlist skip, fixed in M13-AI-002) | `AddNewsTest` (3 gate tests) | Breaking stories bypass gate entirely; checklist modal unwired (server hard-blocks); "new facts checked" leg impossible (FR-AI-005) |
| FR-AI-007 [M] | AI settings per desk | partial | `AiSettings` (preeditEn/Bn/Photos, stylePrompt, monthlyCap → `settings.ai.desk`) | `AiSettingsTest` (12), `AiServiceTest` | Usage bars fed by hardcoded fallbacks (188200/97600/26600 + fake call counts); Photos toggle has no consumer |
| FR-AI-008 [M] | Auto-publish (default OFF) | partial | `AiSettings` danger-zone modal + `autoCats`; gate-skip in `StoryService` | `AiSettingsTest`, `AddNewsTest` allowlist tests | **`auto_published` audit path is dead code** — no caller passes `gate='auto'`; gate-skipped publishes log plain `published` |
| FR-AI-009 [M] | Kill switch | partial | `AiSettings::toggleKill` (instant persist, forces autoPublish off, audited), `AiService` rejects calls | `AiSettingsTest`, `AiServiceTest::test_kill_switch_blocks` | AI buttons don't hide on story form + no explanatory note (spec) |
| FR-AI-010 [S] | AI English search tags | missing | `en_tags` column + manual edit only | none | No generator/job/confirm flow; tags displayed despite "search-only, never display" |

---

## 3. FR-MED — Media library & desk review / AP photos

| FR | Title | Status | Evidence | Tests | Deviations |
|----|-------|--------|----------|-------|------------|
| FR-MED-001 [M] | Library grid | partial | `PhotoManager`, `MediaRepository::paginateAssets` | `PhotoManagerTest`, `MediaRepositoryTest` | Thumbnails are CSS gradient placeholders (no real derivatives — FR-MED-008); search is SQL LIKE not Meilisearch |
| FR-MED-002 [M] | Workflow tabs with counts | partial | `MediaRepository::getWorkflowTabCounts` (7 tabs) | `PhotoManagerTest`, `MediaRepositoryTest` | No **rejected** tab; tab set renamed (all/field/review/library/packaged/published/embargo) |
| FR-MED-003 [M] | Inspector panel | partial | `selectAsset`/`saveAssetMetadata` inspector | `PhotoManagerTest::test_inspector_*` | Missing event/category/EXIF/review-history fields; inline saves **not audited** |
| FR-MED-004 [M] | Caption, credit & metadata editing | partial | `saveAssetMetadata` (credit template) | same | Category/event not editable; no audit trail ("saved inline and are audited" unmet) |
| FR-MED-005 [M] | Selection & bulk actions | changed | `bulkApprove`/`bulkAssignPackage` + `downloadZip()` Alpine/JSZip | `PhotoManagerTest`, `Api\MediaZipExportTest` | **Admin ZIP is client-side JSZip fabricating gradient PNGs** (synchronous, fake content) — spec: async job; real `ExportMediaZipJob` unwired to admin. Bulk reject missing |
| FR-MED-006 [M] | Field intake approval queue | partial | `getPendingBatches` + approve/reject/re-edit + `MediaReview` + notify | `PhotoManagerTest` (2 intake tests) | Nothing can enter the queue in production: TUS `patch` **discards chunk bytes**; no IntakeService; batches only from `MediaSeeder` |
| FR-MED-007 [M] | Approval consequences | partial | approve → library + `approved_at/by` + auto package assignment | same | `GenerateDerivatives` never dispatched ("derivatives ready" false); not added to search index |
| FR-MED-016 [M] | Media embargo | changed | `embargo_until` column + display badges + tab count | count test only | **Display-only**: no UI to set embargo; embargoed assets **not withheld** from portal/API/ZIP/fan-out; no media embargo lift job |
| FR-MED-008 [M] | Derivative generation | changed | `app/Jobs/GenerateDerivatives.php` | `MediaTest`, `JobExecutionTest` (enshrine stub) | **Stub**: writes path strings, no image/video processing, never dispatched from app code; names deviate (thumb/preview/large vs spec) |
| FR-MED-009 [M] | Desk upload | partial | `PhotoManager::handleUploads` (Livewire upload, MIME/size/sha256) | `TusUploadTest` (8, TUS only) | Production upload ≠ resumable TUS path; `handleUploads` untested; TUS itself stores nothing |
| FR-MED-010 [S] | Duplicate detection | missing | checksum stored only | none | No lookup/warning on ingest |
| FR-MED-011 [S] | AI captions & tags for photos | missing | story-only AI | none | No photo caption/tag suggestion or confirm flow |
| FR-MED-012 [M] | Media search | changed | SQL LIKE in `paginateAssets` | `MediaRepositoryTest` | Spec mandates Meilisearch media index + Bangla mitigations — absent (`ProcessIndexOutbox` drops non-story rows) |
| FR-MED-013 [M] | Download & tracking | partial | `PresignedUrlService` + `downloads` ledger | `MediaTest`, `CoreServicesTest` | Staff downloads intentionally unlogged (no staff "who" column); AP `downloadOriginal` serves no file |
| FR-MED-017 [S] | Per-asset download analytics | changed | "Client usage" block in `photo-manager.blade.php` | none | **Hardcoded fabricated percentages**, never queried from `downloads` ledger |
| FR-MED-014 [M] | AP feed browsing | changed | `ApPhotoManager` browse/filters/lightbox | `ApPhotoManagerTest` (12) | **`syncNow()` is fake** (hardcoded counter + static log); `attachToStory` flips `source='ap'` to `status='library'`, breaking "never mixed into UNB library" |
| FR-MED-015 [M] | AP usage | partial | attach-to-story + download counter | `ApPhotoManagerTest` (2) | "Download per license" returns no file; staff AP downloads unlogged; license history falls back to demo rows |

---

## 4. FR-FLD — Field apps (all deferred per DEC-008)

| FR | Title | Status | Notes |
|----|-------|--------|-------|
| FR-FLD-001…010 | Offline-first PWA, send queue, assignments, batch upload, resumable upload, submission tracking, MoJo filing, server time, device binding, field stats | deferred | `Modules/Field` services (`AssignmentService`, `FieldIngestService`) deferred; `assignments`/`media_batches.assignment_id`/`devices` tables retained for schema parity (DEC-011 notes) |

---

## 5. FR-DST — Distribution

| FR | Title | Status | Evidence | Tests | Deviations |
|----|-------|--------|----------|-------|------------|
| FR-DST-001 [M] | Package management | partial | `PackagesManager`, `PackageRepository` | `PackagesManagerTest` (10), `PackageRepositoryTest` (11) | `kind` hardcoded (bundle/news), not user-selectable; code auto-generated |
| FR-DST-002 [M] | Entitlement definition | done | `Package.entitlement_filter` JSONB → `EntitlementResolver` → fan-out + Meili tenant tokens (single source) | `DistributionTest`, `EntitlementResolverTest` (5) | — |
| FR-DST-003 [M] | Client subscriptions | done | `ClientPackage` starts/ends/status, expiry cuts entitlements | `EntitlementResolverTest`, `BillingTest`, `SeedWorkflowTest` | (see gap: suspended client delivery — FR-CLT-001) |
| FR-DST-004 [M] | Delivery channels | partial | `ClientChannel` api/ftp/webhook, `FtpDiskFactory`, `WebhookSigner`, credential encryption | `Ftp*Test` (16), `DeliverySettingsTest` (11) | Secrets in config JSONB via `Crypt` — **not vault references** (NFR §8); email channel via `clients.notes` JSON |
| FR-DST-005 [M] | Publish fan-out | done | `FanoutStory` per entitled client-channel (idempotency key) | `DistributionTest`, `FanoutAdvancedTest`, `EmailFanoutTest` | Media-package approval does not enqueue delivery |
| FR-DST-006 [M] | Delivery guarantees | partial | idempotency key, `payload_hash`, tries/backoff, auto-pause after N | `FanoutAdvancedTest` (idempotency/auto-pause) | **Client contact not notified on auto-pause** (spec requires); auto-pause per-channel not per-client |
| FR-DST-007 [M] | Distribution log | partial | `DistributionLog` (status filter, search, retry) | `DeliveryRepositoryTest` (8) | **No date-range filter** (spec: client/channel/status/date-range); no `DistributionLogTest`; deliveries partitioning unimplemented |
| FR-DST-008 [M] | Corrections & kill notices | missing | `FanoutStory` **exits early unless `status === 'published'`** | `FanoutAdvancedTest::test_killed_story_no_fanout` (enshrines gap) | **Kill/correction notices are never delivered**; no update fan-out on re-edit; no `story.killed`/`story.updated` webhook events |
| FR-DST-009 [S] | Service configuration | partial | `ServiceConfig`/`WireServiceView` (name/description/enable) | `ServiceConfigTest`, `WireServiceViewTest` | Output formats + delivery windows absent from service config |

---

## 6. FR-CLT — Clients

| FR | Title | Status | Evidence | Tests | Deviations |
|----|-------|--------|----------|-------|------------|
| FR-CLT-001 [M] | Client management | partial | `ClientService` onboard/pause/resume/deactivate, `ClientsManager` | `ClientsManagerTest` (15), `ClientRepositoryTest` (17) | **`pauseClient` does not stop delivery** — `FanoutStory` never checks `clients.status`; UI edit of country/timezone/billing contact not exposed |
| FR-CLT-002 [M] | Portal user management | done | `PortalAccountService` invite/deactivate/reactivate/resend/role | `PortalAccountTest` (17) | — |
| FR-CLT-003 [M] | API keys | done | `ApiKeyService` issue/rotate/revoke/authenticate/hasScope; hashed storage | `ClientApiKeyTest` (6), `ApiKeyServiceExtendedTest` (8) | Raw key re-revealable via `toggleRevealKey` — spec "shown once" |
| FR-CLT-004 [S] | Client 360 | partial | ClientsManager drawer (overview/channels/package/activity) | `ClientsManagerTest` (2 drawer tests) | Download/usage stats are **mock data** from `clients.notes` JSON, not the `downloads` ledger |

---

## 7. FR-PRT — Client portal

| FR | Title | Status | Evidence | Tests | Deviations |
|----|-------|--------|----------|-------|------------|
| FR-PRT-001 [M] | Portal login | done | `PortalAuthController` + `EnsurePortalSession` + `portal/components/LoginModal.tsx` | `Api\PortalAuthTest` (15) | — |
| FR-PRT-002 [M] | Home feed | done | `PortalController@feed` + `portal/app/page.tsx` | `PortalApiTest` (28), `PortalFeedEntitlementTest`, `FeedTombstoneTest` | — |
| FR-PRT-003 [M] | Superfast search | partial | `TenantTokenIssuer`, `index_outbox`, `portal/lib/search.ts` | `SearchTenantTokenTest`, `PortalApiTest` search-token suite | **Portal UI never calls `searchStories`** — search goes through Laravel `?search=` ILIKE; archive index never queried/labeled |
| FR-PRT-004 [M] | Story download | done | `DownloadGateService` (entitlement→quota→wire format→ledger) | `Api\StoryDownloadTest` (8), `DownloadGateServiceTest` | — |
| FR-PRT-005 [M] | Media download | done | `MediaController@clientPresigned` + presigned URLs + quota | `MediaTest`, `QuotaEnforcementTest` | — |
| FR-PRT-006 [M] | Feed API | done | `ClientFeedController` (cursor, cache, ISO8601) + per-key throttle | `ClientFeedEntitlementTest`, `FeedTombstoneTest` | — |
| FR-PRT-007 [M] | Local-time rendering | changed | `portal/app/page.tsx`, `story/[id]/page.tsx` | none | **Inverted**: hardcoded `timeZone: "Asia/Dhaka"` instead of consumer IANA tz + secondary Dhaka label |
| FR-PRT-008 [M] | Bangla content | missing | backend `language=bn` filter works | `SeedWorkflowTest` (backend only) | No Bengali typography, no Bangla UI labels in portal |

---

## 8. FR-ACC — Access & settings

| FR | Title | Status | Evidence | Tests | Deviations |
|----|-------|--------|----------|-------|------------|
| FR-ACC-001 [M] | Role management | done | `RolesManager` matrix + locked system-role guards + superadmin | `RolesManagerTest`, `SuperadminTest` | — |
| FR-ACC-002 [M] | Staff management | partial | invite/deactivate/activate/resend in `RolesManager` | `RolesManagerTest` (3) | **Deactivation does not kill sessions or revoke device tokens**; invite/resend are toast-only (no mail) |
| FR-ACC-003 [M] | Permission enforcement | done | `rbac:module,action` on all 13 admin routes + `RbacService` + API key scopes | `RbacServiceTest` (13), `RbacEndpointTest`, `SuperadminTest` | — |
| FR-ACC-005 [C] | Preferences | done | `Preferences` (tz/date_format/density) + `DisplayPrefs` helper | `PreferencesTest` (9) | — |
| FR-ACC-004 [M] | AI administration | partial | `AiSettings` (RBAC-enforced) | `AiSettingsTest` | Inherits FR-AI-007/008/009 gaps (fake usage bars, unlogged auto-publish, no UI hide) |

---

## 9. FR-NTF — Notifications & audit

| FR | Title | Status | Evidence | Tests | Deviations |
|----|-------|--------|----------|-------|------------|
| FR-NTF-001 [M] | Editorial notifications | partial | `NotificationService` (review/status/handover/note/media-decision + 300s burst-collapse), DB+mail channels | `EditorialNotificationTest` (9), `StaffEmailNotificationTest` (8) | Email skips notes/approved/field-batch decisions (tests assert absence); handover notifies one party only; sync `sendNow`, no worker |
| FR-NTF-002 [M] | Notification center | partial | `NotificationCenter` (mark read, filter, deep links) + topnav bell | `NotificationCenterTest` (8) | Deep links land on **list pages**, not the story/batch/asset |
| FR-NTF-003 [M] | Audit trail | partial | `AuditLogRepository` (ip/ua/correlation/diff), `AuditQueryService`, `AuditLogBrowser` | `AuditLogWiringTest` (6), `AuditBrowserTest`, `AuditLogRepositoryTest` (9) | **API-key issue/rotate/revoke never audited**; AI gate bypasses unlogged (FR-AI-008 dead path); some role diffs store HTML message only |

---

## Undocumented deviations flagged

1. **`FanoutStory` early-return for non-published stories** silently contradicts FR-DST-008/FR-NWS-016 ("triggers a correction/kill notice") — no ADR covers this.
2. **Breaking stories bypass the AI publish gate** (`StoryService` `&& ! $story->is_breaking`) — FR-AI-006 allows skip only for auto-publish+allowlist.
3. **`en_tags` displayed** in PhotoManager despite FR-AI-010 "never display".
4. **AP assets flip to `status='library'`** on attach — breaks FR-MED-014 "never mixed into the UNB library".
5. **`auto_published` gate path unreachable** — FR-AI-008's audit promise silently unmet.
6. **Staff media downloads unledgarable** — `downloads.client_id` NOT NULL, no staff column (FR-MED-013).
7. **Hardcoded demo fallbacks presented as live data** — AP sync counter/logs, client-usage percentages, AI usage bars, delivery license history (quality-gate "no fake success").

---

## Prioritized critical gaps

### P0 — fix before production launch
1. **Kill/correction notices never delivered** (FR-DST-008, FR-NWS-016) — wire clients never learn a story was killed/corrected. *Editorial/legal exposure.*
2. **Media ingest pipeline is a facade** (FR-MED-006/009) — TUS `patch` discards bytes; no intake service; production upload path non-functional.
3. **Media embargo not enforced at delivery edges** (FR-MED-016) — embargoed assets leak via portal/API/ZIP/fan-out.
4. **Staff deactivation leaves sessions/devices alive** (FR-ACC-002) — security gap.
5. **Suspended clients keep receiving deliveries** (FR-CLT-001) — `FanoutStory` ignores `clients.status`.

### P1 — fake-success / broken spec promises (quality-gate violations)
6. Fake surfaces: admin ZIP gradient PNGs (FR-MED-005), AP sync counter (FR-MED-014), hardcoded analytics (FR-MED-017/004/FR-CLT-004), AI usage bars (FR-AI-007).
7. `auto_published` audit path dead (FR-AI-008) + gate bypasses unlogged (FR-NTF-003).
8. Derivatives stub, never dispatched (FR-MED-008) → no real thumbnails anywhere.
9. Portal search not wired to Meilisearch (FR-PRT-003); archive index unused.
10. Local-time rendering inverted (FR-PRT-007).

### P2 — completeness
11. FR-AI-005 new-facts warning + FR-AI-010 AI English tags — missing.
12. FR-PRT-008 Bangla portal typography/UI — missing.
13. FR-NWS-018 pipeline kanban view — interim list filter only.
14. FR-DST-007 distribution log date-range filter; FR-DST-006 auto-pause notification.
15. FR-NWS/FR-AI compare & checklist modals unwired DOM (FR-AI-003/006 UX).
16. FR-ACC-002 invite mail; FR-NTF-001 email coverage for notes/approved/decisions.

**Recommended immediate fixes (small, high-value):** #1 (fan-out on kill + correction events), #5 (`clients.status` check in `FanoutStory`), #7 (pass `gate='auto'` + audit bypasses), #4 (session purge on deactivate). The media ingest/derivatives rebuild (#2/#8) is the largest workstream — recommend a dedicated design task.
