# Testing coverage gaps — E2E, unit, integration

**Date:** 2026-10-04 · **Scope:** findings only (no fixes).  
**Closure plan:** `docs/tasks/README.md` §21 (`M14-COV-001`…`028`). Billing (`FR-BIL` absent) and field PWAs (`DEC-008`) are out of that plan.  
**Sources:** `tests/e2e/*.spec.ts` (38 specs), `tests/Unit` (3 files), `tests/Feature` (~101 files), `playwright.config.ts`, `phpunit.xml`, `routes/console.php`, `app/Jobs`, `app/Console/Commands`, `app/Services`.

This is a coverage and quality audit, not a product-FR implementation audit. Implementation status lives in `docs/fr-cross-check-report.md`.

---

## Snapshot

| Layer | What exists | What it actually proves |
|---|---|---|
| E2E | ~38 Playwright specs, ~245 `test()` calls | Admin chrome, a few API contracts, one publish-to-portal-feed path, media review with DB checks. Most specs are DOM/toast smoke. |
| Unit | 13 methods in 3 files | `RevisionService::diff` (5) and `Assignment` model (7). `ExampleTest` is `assertTrue(true)`. |
| Feature / integration | ~776 `test_` methods | Broad HTTP/Livewire coverage. Multi-step domain chains are split across tests or faked. No coverage threshold. |

`phpunit.xml` includes `<source>app</source>` but has no coverage report, threshold, or `failOnWarning`. CI runs `php artisan test` with coverage off. Default suite is sqlite `:memory:`; CI is Postgres. One search test skips on sqlite (`PortalApiTest`).

There is no `tests/Integration` suite. "Integration" below means Feature tests that cross services, jobs, and tables — and the chains those tests do not close.

---

## 1. E2E — coverage gaps

### Solid

- Unauthenticated API 401s, Daily Star vs Prothom Alo entitlement filter, killed tombstone shape, download ledger counts, 2-rpm 429, suspended key (`tests/e2e/api-media-billing.spec.ts`).
- Media approve / reject / reedit writes `MediaAsset` / `MediaReview` (`tests/e2e/media-review-pipeline.spec.ts`).
- One unique-headline publish lands in `/admin/news/en` and `/api/v1/portal/feed` (`tests/e2e/editorial-flow.spec.ts`).
- Takeover toast, stale-version banner, notification, audit row (`tests/e2e/editorial-concurrency-locking.spec.ts`) — simulated, not two browsers.

### Missing or name-only journeys

| Journey | Evidence | Gap |
|---|---|---|
| Editorial state machine | `editorial-flow.spec.ts:26` title is `draft → in_review → approved → published`. Body fills headline, jumps to step 3, clicks `#publishBtn`. | No `sendToReview`, no editor, no `changes_requested`. Grep of `tests/e2e` finds no `sendToReview`, `quickPublish`, or `embargo`. |
| Embargo then lift | No e2e sets `embargo_until`. | Story never proven absent from feed, then present after the worker. |
| Kill / correction fan-out | Kill coverage is a pre-seeded feed row in `api-media-billing.spec.ts`. | No desk user kills or corrects a story a client already received. |
| Unpublish / live toggle | `news-list-faithful.spec.ts:208-214` clicks `label.switch` then `waitForTimeout(1000)` with no expect. | `NewsList::togglePublish` is a real write. Bulk Publish / Delete are visibility-only. |
| Subscription expiry and overlap | Package UI in `packages-faithful.spec.ts`. | No expiry cutting delivery + portal + API. No union of overlapping packages. |
| Entitled vs unentitled portal UI | API filter is tested. Portal feed boots from `INITIAL_STORIES` (`portal/app/page.tsx`). Story page falls back to mock on API failure (`portal/app/story/[id]/page.tsx:14-19`). | A green portal UI run can be prototype data. |
| API key rotation overlap | `delivery-settings-faithful.spec.ts` asserts toast "Old key revoked". | Old key is not called (401) and new key is not called (200). |
| Two-browser stale save | `editorial-concurrency-locking.spec.ts` bumps version via `seed-data.php`. | No second Playwright context. No 409 + merge prompt on a real save. |
| Bangla desk publish | `/admin/news/bn` is a heading check. Portal Bangla uses `/story/bn1` and skips on "Story not found". | No Bangla story created, published, and read back with notes absent from the client payload. |
| Delivery retry | `distribution-log.spec.ts` asserts toast "Queued for retry". | No attempt count, status change, or queue row. |
| Client onboard / pause | Wizard closed without `activateClient`. Pause modal cancelled. | No subscription or API-key assertion. |
| Staff auth edges | `web-auth-rbac.spec.ts` checks URL still `/login` after `waitForTimeout(1000)`. | No error text. No logout, forgot-password, or reset. `routes/auth.php` reset/verify have no spec. |
| Portal password reset | `POST /api/v1/portal/reset-password` has no spec. | Login modal wrong-password is covered. Completion is not. |
| Tus / AI HTTP | Unauthenticated 401 only. | AI generation is Livewire + stub string "AI Generated headline", not `OpenAiProvider`. |
| Cursor pagination | `portal-story-pagination-search.spec.ts:20` is named `?since` and never sends `since`. | Only checks `limit=1` on the portal feed. |
| Meilisearch | `portal-search.spec.ts` checks JWT shape; throttle accepts 200 or 429. "Rizvi" search hits mock copy in `portal/lib/mockData.ts`. | Browser search is not an index hit. |
| Billing | `api-media-billing.spec.ts` name. | No `FR-BIL` in the FRD. No invoice journey. |
| Field PWAs | — | Deferred (`DEC-008`). Desk-side field intake only. |

### Admin routes with no journey

Smoke loop in `ui-admin-auth.spec.ts` omits `/admin/audit`, `/admin/notifications`, `/admin/service/{en,bn}`, `/profile`. Those pages have their own specs, but the specs read seeded rows or DOM, not a mutation.

Livewire methods with no e2e call: `AddNews::sendToReview`, `quickPublish`, autosave restore, saved Bangla story; `NewsList::bulkPublish`, `bulkDelete`, `deleteStory`, `export`; `ClientsManager::activateClient`, `confirmPause`, `confirmDeactivate`, `applyPackageChange`.

### FR families vs e2e

| Family | E2E |
|---|---|
| FR-NWS | Partial. Wizard chrome + one publish shortcut. Missing autosave restore, Bangla authoring, full state machine, unpublish, embargo, quick publish. Concurrency is simulated. Notes are DOM-only. |
| FR-DST | Partial. Package CRUD UI. Entitlement and tombstone are reads of seed data. Missing publish fan-out, backoff/auto-pause, subscription expiry, channel delivery proof. |
| FR-PRT | Partial. Login modal and guest chrome. Feed/search UI is mock-backed. Download UI is route-mocked (`portal-ui.spec.ts`). |
| FR-MED | Partial. Review pipeline with DB checks. Missing embargo, derivatives, real desk upload, duplicate detection, AI captions, AP usage ledger. |
| FR-AI | Partial. Settings persist, kill-switch banner, publish-gate toast. Missing new-facts warning, raw-vs-AI compare, English tags, token-budget exhaustion, allowlisted auto-publish. |
| FR-ACC | Partial. Role UI and a few 403s. A saved permission change is not proven on the next request. |
| FR-NTF | Partial. Bell and mark-read. No email, no burst collapse. Audit browser reads seeded rows, not kill/key-issue/AI-gate rows. |
| FR-CLT | Partial. List and portal-user invite/deactivate. API keys proven on the feed, not via regen UI. |
| FR-FLD | Out of scope (DEC-008). |

---

## 2. E2E — qualitative issues

**Shared database, parallel workers.** `tests/e2e/global-setup.ts` runs `migrate:fresh --seed` once. `playwright.config.ts` does not pin `workers` (CI uses `--workers=2`) and sets `retries: 1`. Specs reseed in `beforeAll` / `beforeEach` and swallow seed failures:

- `delivery-settings-faithful.spec.ts` — full `DatabaseSeeder`
- `ai-settings-faithful.spec.ts`, `roles-faithful.spec.ts`, `packages-faithful.spec.ts` — partial reseed
- `notification-center`, `distribution-log`, `audit-browser`, `media-review-pipeline`, `ai-editorial-quota` — `seed-data.php`, empty `catch`

**Shared mutable identities.** `staff-profile.spec.ts` renames `test@example.com` and does not restore it. Concurrency tests expect the string "Test User". `roles-faithful.spec.ts` deactivates Shohel (the lock owner). `admin-portal-users.spec.ts` deactivates the first active portal user, which can be the portal login. `ai-editorial-quota.spec.ts` writes a global kill-switch row.

**Conditional passes hide gaps.**

- `story-history-diff-restore.spec.ts` skips the suite if seed parsing fails; wizard history passes if the button is hidden.
- `portal-bangla.spec.ts`, `portal-time-and-search.spec.ts` skip on "Story not found". Those URLs are mock ids; the page falls back to `INITIAL_STORIES` on API failure, so a green run can be prototype data.
- `portal-auth.spec.ts` and `portal-account.spec.ts` `test.skip()` if login is flaky instead of failing.
- `admin-portal-users.spec.ts:257-267` skips role change if the dropdown is missing.
- `news-list-faithful`, `photo-manager-faithful`, `dashboard-faithful`, `wire-service-faithful`, `clients-faithful` wrap the interesting action in `if (visible)`.

**Mutations without DB/API assertion** (`docs/workflow.md`: every mutating `wire:click` needs a DB or API check):

- News-list live toggle (click + timeout, no expect)
- Distribution retry (toast only)
- Delivery key regen (toast only)
- Client note save (toast only)
- Role save (toast, not `role_permissions`)
- AP attach (CSS class `done`, not `story_media`)
- Portal photo download (route mock + `window.open`)
- Story-reader workflow button checked enabled if visible, not clicked

**Hard-coded waits.** `waitForTimeout` is used heavily in `wizard-full-flow.spec.ts`, `admin-portal-users.spec.ts`, `staff-profile.spec.ts`, `staff-preferences.spec.ts`, and `tests/e2e/helpers/auth.ts`. Not tied to a Livewire response.

**Brittle assertions.** Body-wide regexes. `ui-admin-auth.spec.ts` fails if the word "Exception" appears anywhere. `portal-story-pagination-search.spec.ts` passes if the body matches the fixture headline or `not found` or `Wire`. `portal-search.spec.ts` passes on 200 or 429. "If-None-Match" test never sends that header.

**Fixed headline collision.** `wizard-full-flow.spec.ts` publishes the same NBR headline every run. With `retries: 1`, a failed publish can still pass the feed assertion if a previous attempt inserted that headline.

**Setup.** No `storageState`. Almost every `beforeEach` does a full form login. Portal specs hardcode `http://localhost:3000` while `baseURL` is Laravel `:8000`. `reuseExistingServer: true` can attach to a dirty already-running server after global setup wiped the DB. Default timeout is 30s; wizard flows sit on that edge unless they raise it.

**Role matrix is a handful of negatives.** Exercised: admin, superadmin badge, editor as handover recipient, business-team 403, one uploader 403. Not exercised: editor cannot publish, photographer cannot open roles, Bangla desk scope, expired subscription.

---

## 3. Unit gaps

`tests/Unit` is not a unit suite. `AssignmentModelTest` uses `RefreshDatabase`. The only isolated test is `RevisionService::diff` via unsaved models.

| Class | Isolated unit? | Where behavior is actually hit | Untested or thin |
|---|---|---|---|
| `RevisionService` | `diff` only | `RevisionRestoreTest` (feature) | `snapshot()` field set |
| `StoryService` | No | `StoryWorkflowTest`, `AddNewsTest` | `takeOver` 409 on stale `expectedVersion`; kill does not assert fan-out + index delete together |
| `HtmlSanitizer` | No (feature, no DB) | `HtmlSanitizerTest` | Entity-encoded schemes, `vbscript:`, SVG/`onerror`, CSS |
| `FactGuard`, `WireStyleLinter`, `StubAiProvider`, `WebhookSigner`, `TriggerMatcher` | No | Feature, assertion-heavy | Should be unit tests; they boot the framework |
| `OpenAiProvider` | **None** | — | HTTP parse, error body, token cost, JSON mode |
| `EntitlementResolver` | No | `EntitlementResolverTest` | Not the compiler `FanoutStory` uses. `media_kinds` not in compiled Meili filter |
| `DownloadGateService` | No | 2 feature tests | Both assert non-empty content for a client with no package. No ledger, no 403, no quota throw |
| `PresignedUrlService` | No | 500-without-config only | Happy presign + TTL |
| `WireFeedService` | No | 11 feature tests | `popularRail()` never called |
| `AuditQueryService` | No | `forEntity` checks `total() > 0` | `search()` never called on the service |
| `ClientService` | No | Only via `ClientsManager` | `computeStats`, `clientHasIssue`, filtered query not named |
| `TenantTokenIssuer` | No | One seed call + HTTP | TTL, bad key, null client |
| `NotificationService` | No | `Notification::fake()` | `notifyMediaDecision` has no test caller |
| Repositories | No | `tests/Feature/Repositories/*` | `DeliveryRepository::hasPriorSuccess`, `failedCount`, `dlqCount`, `deliveredCountBetween` |
| Models other than `Assignment` | No | Used as fixtures | `Story` ULID boot / `getSafeBodyHtmlAttribute`, `MediaAsset::isEmbargoed` / `scopeClientVisible` (indirect only), `User::isSuperAdmin`, invoice boot, API-key casts |
| Form requests | No | `FormRequestValidationTest` hits `limit`/`since` and empty profile name | `AiAssistRequest`, `TusCreateRequest`, `LoginRequest` have no request-class tests |
| Policies | n/a | — | No `app/Policies`. RBAC is `RbacService` + middleware, covered at feature level |

Leftovers: `tests/Unit/ExampleTest.php`, `tests/Feature/ExampleTest.php` (`GET /` status 200).

---

## 4. Integration gaps

Pieces exist. The chain does not.

| Flow | What is tested | What is not |
|---|---|---|
| Publish → snapshot → `story_events` → fan-out → outbox `done` | Split across `StoryWorkflowTest`, `AddNewsTest`, `FanoutStory` tests, `ProcessIndexOutbox` tests. `SeedWorkflowTest` is the closest chain and still skips outbox processing and revision/event asserts. | No one test runs publish then both job handlers and asserts delivery row + outbox `done` + version snapshot. `StoryPublished` is `Event::fake()` — dispatch only, no listener. |
| Embargo lift | `SchedulerAndMiddlewareTest::test_embargo_lift_transitions_approved_to_published` counts matching rows, then **updates the story itself**. | Never runs the `embargo-lift` closure in `routes/console.php`. No `story_events`, no outbox insert, no `FanoutStory`, no "still in the future" negative. `Cache::forget('portal:feed:*')` is not a wildcard invalidate and is untested. Publish-time embargo block and Dhaka→UTC input conversion are tested elsewhere. |
| Archive sweep | Same file selects candidates and does not archive. | No status change, no `main` delete + `archive` upsert, no outbox dispatch. |
| Upload janitor | — | `upload_sessions` expiry closure has no test. |
| `delivery:process` | **No test references the command.** | Retry, exponential backoff, max retries, auto-pause, inactive channel skip, FTP skip, missing story. |
| Webhook non-2xx | Fan-out auto-pause after thrown exceptions is tested. | `FanoutStory` records a channel failure on non-2xx but can leave the delivery `queued`. The untested command is the only retry path. |
| Kill / correction | `FanoutAdvancedTest` covers kill notices to prior `sent` recipients and idempotency. | `StoryService` kill → job handle → delivery is not one test. Kill does not write an index delete. Email kill path untested. Correction does not re-check entitlement. |
| Two entitlement compilers | `EntitlementResolver` unions languages/categories and checks `ends_at` (`EntitlementResolver.php:18`). | `FanoutStory` matches each `client_packages` row with AND and **does not read `ends_at` / `starts_at`** (no matches in that file). `SendStoryEmail` sends to the client id it is given and does not consult packages. Overlap can show a story in the portal and never deliver it, or deliver after expiry. That divergence is untested. |
| Index delete | `ProcessIndexOutbox` has an `op === 'delete'` branch. | No test hits it. Archive-sweep delete rows are also untested. |
| Outbox lag | `DeliveryRepository::getLagSeconds` may be covered at repo level. | `monitor:outbox-lag` / `CheckOutboxLag` has no test. `ProcessIndexOutbox::lagSeconds()` untested. |
| API key overlap | Both keys work immediately after rotate. | No time travel past `expires_at`. |
| Quota | `QuotaServiceTest` + API enforcement. Best-covered billing rule. | Not tied to invoice period boundaries. |

Jobs that do have tests: `FanoutStory`, `ProcessIndexOutbox` (upsert only), `GenerateDerivatives`, `GenerateEnTags`, `PushFtpDelivery` (disk and formatter mocked away), `SendStoryEmail`, `ExportMediaZipJob`.

Commands: `BackupDatabase` / `BackupList` covered. `EncryptFtpCredentials` called from one encryption test. `ProcessDeliveriesCommand` and `CheckOutboxLag` uncovered.

`MediaUploaded` has no test and no listener. `SendPortalResetPassword` is not referenced; portal reset is HTTP-only in `PortalAuthTest`.

---

## 5. Feature-test qualitative issues

**Tests that do not run the behavior they name**

- `SchedulerAndMiddlewareTest` embargo lift and archive sweep (see above).
- `DistributionTest::test_delivery_payload_hash_stable` hashes a string in the test; does not call fan-out.
- `DistributionTest::test_entitlement_filter_is_single_source` asserts factory JSON only.
- `DownloadGateServiceTest` — content non-empty, no denial, no `downloads` row.
- `AuditBrowserTest::test_audit_browser_renders_filters` — render/see, not filter correctness.
- `DatabaseConnectionTest` — config shape plus `SELECT 1`. Does not prove replica routing.
- `PushFtpDeliveryTest` mocks `FtpDiskFactory` and `WireFormatFactory`. Status update is tested; wire bytes are not.
- `StoryPublishedBroadcastTest` fakes the event. There is no listener to prove.

**Assertion shape.** Many admin tests are `assertOk` / `assertSee`. HTTP 409 appears about once (TUS offset), while stale story save is an exception in `StoryWorkflowTest`, not a Livewire 409 response. Authz is reasonably deep for staff RBAC and thin for portal-session vs API-key confusion, and for Livewire actions that the UI hides but tests do not deny server-side.

**Sqlite vs Postgres.** `PortalApiTest::test_feed_filters_by_search_query` skips on sqlite because of `ILIKE`. Default `phpunit.xml` is sqlite, so that path is CI-only.

**Architectural.** ~776/789 tests are Feature. Pure rules boot the framework. A regression in a private branch (email entitlement, webhook non-2xx, scheduler closures, index delete, fan-out `ends_at`) is invisible unless an HTTP test happens to hit it. Coverage is neither collected nor gated.

---

## 6. Highest-risk gaps (act on these first)

1. **Embargo lift and archive sweep** — the tests that claim coverage do not run `routes/console.php`. A broken lift still passes. → `M14-COV-002` (invokables + real-closure Feature tests) + `M14-COV-018` (E2E embargo → lift).
2. **`delivery:process` and webhook non-2xx** — production retry/backoff/auto-pause has zero command tests. Fan-out can leave deliveries `queued`. → `M14-COV-003` (`ProcessIndexOutbox`+`FanoutStory` non-2xx mark `failed`) + `M14-COV-022` (E2E retry DB assert).
3. **Two entitlement compilers** — portal/search honor union + `ends_at`; fan-out does not; email ignores packages. Untested divergence. → `M14-COV-004` (single `EntitlementResolver::clientAllowed` for fan-out + email + download) + `M14-COV-020` (E2E expiry + union).
4. **Kill/correction is not one pipeline** — notices, feed tombstones, and index delete can drift with no test catching it. → `M14-COV-005` (Feature pipeline + outbox `op=delete`) + `M14-COV-019` (E2E kill/correction/unpublish).
5. **E2E state machine is a shortcut publish** — the named editorial journey never sends a story to review. → `M14-COV-017` (full draft → in_review → changes_requested → approved → published across two contexts).
6. **Portal UI can pass on mock data** — story page and feed fall back to `INITIAL_STORIES`; several specs skip instead of fail. → `M14-COV-001` (portal webServer sets `NEXT_PUBLIC_DISABLE_MOCK_FALLBACK=1`) + `M14-COV-021` (real-public-id render; mock-id "Story not found").
7. **Shared e2e database** — parallel workers plus reseed and shared user mutation make green runs order-dependent. → `M14-COV-001` (fail-closed seed; throwaway fixtures via `seed-data.php`; shared-user restore in `afterEach`).
8. **`OpenAiProvider` and index `delete` / `monitor:outbox-lag`** — live provider failures and search removal/lag alerts are untested. → `M14-COV-008` (`Http::fake` unit tests) + `M14-COV-006` (`ProcessIndexOutbox` `op=delete` + `monitor:outbox-lag` exit codes) + `M14-COV-016` (allowlist CI check).
9. **API key overlap window** — both keys work at t=0; nothing proves the old key dies after the overlap hour. → `M14-COV-007` (`Carbon::setTestNow` past `expires_at` + HTTP 401) + `M14-COV-022` (E2E regeneration + 401 after expiry).
10. **Mutating UI clicks without DB/API asserts** — live toggle, retry, key regen, role save, AP attach. → `M14-COV-025` (DB/API assertion in every mutation) + `M14-COV-019` (live toggle → feed).

Out of scope and explicitly waived: `FR-BIL` (no such FR in the v1 spec) and field PWAs (`DEC-008`).

Closed on 2026-10-04. See `docs/tasks/README.md` §21 for the build order and §28 of the task board for the closure record.
