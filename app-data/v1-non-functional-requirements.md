# UNB Wire — v1 Non-Functional Requirements (NFR)

> Scope: everything the redesigned UI already implies — news editor, English/Bangla desks,
> UNB Photos (DAM), AP Photo Manager, client portal, packages & distribution, FTP push,
> RBAC, audit log, MoJo field app, photo field app + desk approval queue.
>
> This doc is a brainstorm-grade first pass, not a signed-off spec. Each section ends with
> a **v1 call** — what we commit to for the first production release vs what we defer.

---

## 0. Load model (working assumptions — validate with real numbers)

| Metric | Assumption for v1 |
|---|---|
| Stories filed | 300–600/day across English + Bangla |
| Photos uploaded | 1,500–4,000/day (field + desk), avg 8–15 MB original |
| Video clips | 20–60/day, 50–500 MB |
| Newsroom staff concurrent | 60–120 editors/uploaders |
| Client organizations | 50–200 (newspapers, TV, portals, FM) |
| Client feed polls | every 1–5 min per client → ~30k–200k feed reads/day |
| Client portal sessions | 1,000–5,000/day, search-heavy |
| Breaking-news multiplier | 10–50× normal traffic for hours (elections, disasters, cricket) |

**Character: heavily read-biased.** For every 1 story written, it is read/served/distributed
hundreds of times. Writes come in bursts (field upload spikes during events). Plan for
**read : write ≈ 100 : 1** system-wide, with write bursts that must never be lost.

---

## 1. Search — the heaviest subsystem

Search appears in at least **five** places, with different shapes:

| # | Surface | What is searched | Shape |
|---|---|---|---|
| 1 | Newsroom archive | full story text, headline, desk, category, date, status | faceted filters + relevance |
| 2 | Client portal | published stories + photo captions | faceted + typo-tolerant, entitlement-filtered |
| 3 | Photo DAM | captions, IPTC keywords, photographer, location, package | filter-heavy, instant |
| 4 | Editor "related stories" + link-by-title | headlines/IDs | prefix/instant |
| 5 | Field apps (desk lookup) | recent own batches, assignments | small, can stay in DB |

### Recommendation: Meilisearch for v1 — with one big caveat

**Yes, take Meilisearch for v1** because:
- Typo-tolerant instant search out of the box (portal + DAM feel modern immediately)
- Filters/facets (desk, date, package, status, photographer) are first-class
- Tiny ops footprint vs Elasticsearch/OpenSearch (single binary, one index per surface)
- Ranking rules are configurable per index (freshness boost for wire content)

**The caveat — Bangla.** Meilisearch tokenizes Bengali as whitespace-separated words
with no Bengali stemming/stopwords. Bangla morphological variants (সাংবাদিক / সাংবাদিকের /
সাংবাদিকদের) won't match each other. For a Bangla-first news agency this is a real risk.

**v1 call:**
- Week 1–2 spike: index 50k real UNB Bangla stories in Meilisearch and judge recall with
  actual desk staff queries. Decide with data, not vibes.
- If Bangla recall disappoints → **plan B without Elasticsearch** (ES is deferred for
  budget, see §17), as a layered mitigation:
  1. **Stemmer-light:** index-time suffix-stripping / variant expansion (সাংবাদিক,
     সাংবাদিকের, সাংবাদিকদের → one root form)
  2. **Curated synonym list** for common Bangla spelling variants (হিলিস/ইলিশ)
  3. **AI-generated English tags, search-only:** every Bangla story gets English
     keyword tags generated in the background (§14 AI job) — stored in a separate
     `en_tags` field, indexed in Meilisearch with its own weight, never shown as
     the article's language. This is the cross-lingual bridge: an English query
     ("hilsa price") surfaces Bangla copy, and Bangla-desk users get a second net.
     Uploaders don't type tags — they confirm AI suggestions in one click (defaults
     over discipline).
  4. Postgres FTS remains the last-resort fallback.
  Together these get 80–90% of a real Bengali analyzer's value at zero infra cost.
- Sync path: Postgres **outbox table → indexer workers → search index** (never index
  from the request path). Search lag budget: story searchable ≤ 5s after publish.
- Entitlements enforced at query time: portal search auto-injects
  `filter = packages IN (client's package)` — a client must never see a headline
  they're not paying for (e.g. "Client Bangla (Without AP)").

### What "superfast for clients + staff" concretely takes

- **All search is served from the search engine, never SQL `LIKE`** — Postgres FTS is an
  emergency fallback only.
- **Index at write time** (outbox → indexer), so search cost is paid once at publish,
  not per query. Index lag ≤ 5s for hot content.
- **Latency budgets:** portal/admin search p95 < 300ms; typeahead/suggest p95 < 100ms;
  facet counts come free from the engine (no extra queries).
- **Entitlement filter injected server-side on every client query** — clients only ever
  search the slice they pay for; the filter is added by our API, never trusted from the client.
- **Ranking tuned per surface:** wire/portal = freshness-heavy (recent first among
  equals); archive = relevance-heavy; DAM = exact keyword/photographer matching.
- **Media is searched via metadata** (caption, IPTC keywords, photographer, location,
  event tag) — no content-based video search in v1; transcripts later unlock it.
- **Scale headroom:** at ~600 stories/day + ~1.5k photos/day we reach ~1M docs/yr.
  A single Meilisearch node handles low millions comfortably → fine for v1–v2 with a
  replica for read HA; revisit sharding at ~5M+ docs.
- **Search is derived data, never the source of truth** — full rebuild from Postgres
  must be a tested, scripted operation (target: full reindex < 1 hr).

---

## 2. Read vs write — per operation

| Operation | R/W | Volume | Notes |
|---|---|---|---|
| Client feed API (latest stories) | READ | very high, constant | cacheable 30–60s, Redis + CDN edge |
| Story read (portal/admin) | READ | high | immutable after publish → aggressive cache |
| Photo thumbnail/derivative serving | READ | very high | CDN, immutable URLs |
| Distribution log views | READ | medium | paginate, archive old partitions |
| Editor draft autosave | WRITE | medium (every 1–2s/keystroke burst per editor) | debounced, tiny payloads |
| Story publish / photo approve | WRITE | low-medium, bursty | triggers fan-out (see §6) |
| Editorial workflow (status change, internal note, handover) | WRITE | low, steady during desk hours | permission-checked; triggers notification fan-out + audit (see §7, §8) |
| Field photo upload | WRITE | bursty, large | resumable, goes to object storage directly |
| Client downloads (ZIP, FTP pulls) | READ | bursty, large | async job + presigned URL, never synchronous |
| Audit log append | WRITE | constant trickle | append-only, can lag |

**v1 call:** read path = Postgres read replica + Redis hot feed + CDN for media.
Write path = single primary. No CQRS ceremony in v1 — just don't serve the hot feed
from the primary.

---

## 3. Media storage & delivery

- **Originals → object storage (S3-compatible).** Never in the DB, never on app servers.
  Estimate: ~3–8 TB/year at current volume; lifecycle rules (originals → cold storage
  after 12 months, derivatives stay hot).
- **Derivatives generated async** on upload (thumb 400px, preview 1600px, webp) by a
  worker queue. The DAM grid never renders originals.
- **CDN in front of everything client-facing.** Thumbnail URLs immutable + versioned
  (`/d/{asset_id}/{v}/thumb.webp`) so cache TTL can be infinite.
- **Field uploads must be resumable** (tus protocol or multipart) — MoJos are on
  3G/4G in the field; a 90%-failed upload that restarts from zero is a lost story.
- Client-side compression option in field apps (long edge 2500px) before upload.

**v1 call:** S3 + tus endpoint + derivative worker + CDN. Lifecycle/tiering is **in**
scope for v1 — see the archival section below.

---

## 4. Data lifecycle & archiving (hot → warm → cold)

Requirement: **everything stays retrievable forever, but >12-month-old content must not
tax the hot path.** No new UI needed — default searches/feeds silently cover the hot
window; crossing into archive happens automatically when someone uses the existing date
filter past 12 months (or an "include archive" flag staff/clients already have the
mental model for).

| Tier | What's there | Where it lives | Search behavior |
|---|---|---|---|
| **Hot** (0–12 months) | all stories, photo metadata, audit | Postgres primary; Meilisearch main index; media originals in S3 Standard; derivatives on CDN | default search — p95 < 300ms |
| **Warm** (>12 months) | story text + asset metadata, audit (7 yrs) | Postgres archive tables / partitions (same cluster ok); separate Meilisearch `archive` index | searched only when the query's date range crosses 12 months — relaxed budget p95 < 1s |
| **Cold** (>12 months) | photo/video **originals** only | S3 Glacier / Infrequent Access | originals restored on demand (minutes–hours ok); **derivatives stay hot forever** (small files, big value) |

- **Nightly archival sweep job** moves rows by `published_at` — idempotent, resumable,
  reversible (a "restore to hot" path for big retrospective events, e.g. election
  anniversary coverage).
- **Two Meilisearch indexes (`main` + `archive`)**, never one giant one — keeps the
  default portal search lean and lets the archive index live on cheaper hardware.
- **Audit log never goes cold** — legal retention beats storage cost.
- **Deletion policy (v1):** nothing is ever hard-deleted except rejected field uploads
  (purge after 30 days) and abandoned drafts (after 30 days). "Delete" everywhere else
  = soft delete.
- **Payoff:** hot DB stays ~12 months lean, backups stay fast, client search stays
  superfast, and a 2019 photo is still findable — just a beat slower.
## 5. Availability & SLOs

News is a 24/7 utility. Clients (newspapers) have hard print deadlines around
**10 PM – 2 AM** — that's our real peak, not office hours.

| Surface | SLO (v1) | Rationale |
|---|---|---|
| Publish → client feed visible | 99.9% within 60s | wire promise |
| Client feed API | 99.95% availability, p95 < 200ms | clients poll it programmatically |
| Portal search | p95 < 300ms | Meilisearch easily does this |
| Editor/admin UI | 99.9% | newsroom can't miss deadline |
| Field upload intake | 99.9% | a lost batch = lost exclusive |
| FTP distribution job completion | 99% within 15 min of schedule | print deadlines |

- **Graceful degradation:** if the write path dies, portal + feed must stay up read-only.
  If search dies, portal falls back to chronological feed.
- **Breaking-news surge:** autoscalable stateless API tier; feed is cacheable so surges
  mostly hit CDN/Redis, not the DB.

---

## 6. Distribution pipeline — delivery semantics

The product is literally "deliver the story to every entitled client, on time, once."

- **Fan-out via queue** (SQS/RabbitMQ/BullMQ): publish event → one delivery job per
  client per channel (API push, FTP, email alert).
- **Idempotency keys** per (story, client, channel) — retries must not duplicate
  a story on a client's FTP.
- **Retry with backoff + dead-letter queue**; FTP failures (like the Jugantor one in
  the UI) auto-alert the desk.
- **Delivery receipts** stored per attempt → powers the distribution log UI.
- **Embargo correctness** is a *functional-correctness NFR*: an embargoed item must be
  impossible to distribute before lift time — enforce in the delivery worker, not just
  hidden in UI. Scheduled release precision: ±60s.

**v1 call:** queue-based fan-out with idempotency + receipts from day one. This is the
core business promise; do not ship "cron loops over clients."

---

## 7. Async jobs — nothing slow runs inside request/response

**House rule:** any work that takes > ~100ms, touches an external system (SMTP, FTP,
S3, CDN, search), or can fail independently of the user's click runs in a **worker**.
The HTTP request only validates → writes state → enqueues a job → returns a job ID.
The UI polls or gets a socket event for completion (the ZIP button + field upload
progress in the prototypes already behave this way).

### System-wide job inventory

| Job | Trigger | Failure handling |
|---|---|---|
| **Email sending** (invites, client alerts, digests) | user action / schedule | retry ×5 w/ backoff → DLQ; provider failover |
| **Distribution fan-out** (FTP push, API push, webhooks per client) | story publish / package release | per-client retry + DLQ + desk alert (§6) |
| **Photo/video derivatives** (thumb, preview, webp) | asset upload/approve | retry; asset shows placeholder until ready |
| **Search indexing** (outbox → Meilisearch main/archive) | publish / edit / approve / archive | lag alarm > 30s; fully replayable |
| **AI pre-edit generation** (headlines, rewrite, tags, BN→EN) | editor request from AI desk | ≤10s p95; idempotent per story+prompt version; token cost logged (§14) |
| **AI tag generation** (English search tags for Bangla stories) | Bangla story save/publish | background, ≤60s; searchable even if it lags — tags catch up (§1) |
| **Editorial notifications** (review requested, note added/replied, approved/changes-requested, handover) | workflow event on a story | in-app + email, ≤60s; idempotent per event; collapses bursts (one mail per thread per 5 min) |
| **ZIP / bulk export builds** | client/staff request | async build → presigned URL → notify |
| **Embargo lifts** | scheduler (±60s precision) | missed-lift alarm — this one is correctness-critical |
| **Archival sweep** (hot→warm→cold) | nightly schedule | idempotent, resumable, reversible |
| **Usage/download counter rollups** | download events | batched upsert; eventual consistency is fine |
| **CDN cache purge** | re-edit after publish | retry; versioned URLs make purge rarely needed |
| **Notifications** (desk ← field events, approvals) | status changes | best-effort delivery but persisted in-app |
| **Virus/malware scan** on uploads | upload | quarantine until pass; reject + notify on fail |
| **Backup verification / restore drill** | weekly schedule | alert on failure — untested backups don't count |

### Worker platform requirements

- Durable queue with visibility timeout — **Redis queues + Horizon** (decided, §16)
- **Idempotency keys on every job type** — any worker may run twice
- DLQ + a replay tool the ops team actually knows how to use
- Per-job metrics: depth, age-of-oldest, failure rate; alarm on backlog growth
- Backpressure: if derivatives lag, uploads still succeed (placeholder state), never 500
- Workers are **separate processes from the API** (same codebase is fine) so a worker
  crash never takes down the request path, and each tier scales independently
## 8. Security, access, audit

- **RBAC as designed in roles.html** maps to backend scopes; enforce server-side on
  every endpoint (the UI hides buttons — the API must still say no).
- **Field apps authenticate with device-bound tokens** (long-lived refresh, revocable
  per device) — a lost phone in the field must not become a leak.
- **Client API keys**: per-client, scoped to entitlements, rate-limited (e.g. 60 rpm
  default), rotation support.
- **Audit log is a legal requirement, not a nicety.** Wire agencies get defamation
  threats — we must answer "who wrote/edited/approved/published this exact text, when."
  Append-only, tamper-evident, 7-year retention (align with counsel).
- Story **version history** retained (the editor's revision snapshots → server-side
  versions on every save/publish).
- **Editorial workflow transitions are audited** — draft → in-review → changes-requested
  → approved → published (and direct publish): who, when, from→to. **Shift handovers
  are chain-of-custody events**: "taken over by X from Y at T" is always recorded.
- **Internal notes are newsroom-only and immutable** — never editable after posting,
  never exposed on any client-facing API or feed output; excluded from search indexes
  that clients can reach.
- **Concurrent editing:** one story, two editors — optimistic locking on a revision
  version (stale save → 409 + merge prompt), plus a soft edit-lock indicator
  ("Maria is editing now"). Handover force-releases the lock.
- Standard: TLS everywhere, OWASP ASVS pass, secrets in a vault, signed URLs for
  private media.
- Data residency: expect government/PSB clients to ask where data lives — prefer
  a region that keeps content in-country or document why not.

---

## 9. Offline-first field apps

MoJo/photo apps are used on moving buses, in rallies, during load-shedding:

- **Outbox pattern** (already in the UI): queue locally, auto-drain on reconnect.
- Local persistence: IndexedDB, survive app kill/phone reboot.
- Conflict policy: last-write-wins for metadata; uploads are immutable (no conflict).
- Bandwidth: total page weight < 300 KB, no framework bloat; photo compression before
  upload; voice notes capped at 2 min.
- Works on ₹15k Androids: test on low-end devices, not just iPhones.

---

## 10. Bangla/i18n correctness

- **Unicode NFC normalization** on ingest for all Bangla text (mixed normalized/
  unnormalized text silently breaks search match and dedup).
- Bangla collation/sorting defined (বাংলা alphabetical ordering for bylines/categories).
- Fonts: web fonts must cover Bengali glyphs with graceful fallback; headline fields
  must not clip conjuncts (যুক্তাক্ষর).
- Dates: UI shows Bangla calendar context optionally — full time rules in the next
  section (§11).

---

## 11. Time & timezone handling

Uploaders are all in Bangladesh; consumers are anywhere on earth. Rule of thumb:
**one clock stores, many clocks render.**

- **Storage: UTC only**, `timestamptz` everywhere. No local-time columns, ever.
- **API contract: ISO 8601 with explicit offset** (UTC preferred). Clients never parse
  server-local strings; we never emit them.
- **Editorial time ≠ publication time — store both.** Dateline = where/when the event
  happened (displayed Asia/Dhaka for staff); `published_at` = UTC. A raid that happened
  "last night" in Khulna is consumed "this morning" in New York — the UI must never blur
  the two.
- **Client portal renders in the consumer's timezone** (browser IANA tz via `Intl`),
  clearly labeled, with the original Dhaka time shown as secondary/hover text. Staff
  surfaces default to Dhaka time.
- **Embargoes and scheduled publishing carry explicit timezones.** Wire copy states it
  ("embargo lifts 18:00 Dhaka / 12:00 UTC"); the DB stores UTC; workers compare in UTC
  only. An embargo is exact — "6 PM" without a zone is a bug (§6).
- **DST:** Bangladesh has none; many client countries do. Never hand-compute offsets —
  IANA tz database only (PHP `DateTimeZone`/Carbon server-side, `Intl` browser-side).
- **Field apps: device clocks are unreliable** (dead battery, manual change). Server
  timestamps are authoritative on ingest; device time is display-only.
- **Date boundaries are per-audience:** "today's stories" means the Dhaka calendar day
  for the newsroom, the client's own day in the portal. Feed query params (`since`,
  `until`) are always UTC.
- **Scheduler jobs** (archival sweep, digests) are *defined* in Dhaka wall-clock
  ("run at 02:00 Dhaka") but stored/executed as UTC instants — a DST change anywhere
  must not shift a job.
## 12. Observability & ops

- Golden signals per surface: feed API latency, publish→distribute lag, upload failure
  rate, search index lag, FTP job failures.
- **Newsroom-facing status page** (even internal-only): desk should see "distribution
  degraded" before clients call.
- Alerting on: distribution lag > 5 min, index lag > 30s, upload error spike, DLQ > 0.
- Structured logs with story/asset IDs → "trace a story from field phone to client FTP"
  in one query.
- Backups: DB PITR, **RPO ≤ 15 min, RTO ≤ 1 hr**. Object storage cross-region
  replication for originals (they're irreplaceable; stories can be retyped, a
  riot photo cannot).

---

## 13. Performance budgets (UI side, already partly met)

| Surface | Budget |
|---|---|
| Admin pages | first paint < 1.5s on office broadband |
| Field apps | interactive < 3s on 3G, < 300 KB JS/CSS |
| Client portal | Core Web Vitals green (LCP < 2.5s) |
| DAM grid | virtualized/lazy thumbnails; 5k assets browsable |

---

## 14. AI pre-edit & auto-publish

The killer feature, and the one with the sharpest failure modes. UI contract is already
prototyped (AI desk in Add News, drawer review, publish checklist, admin AI settings);
these are the system requirements behind it.

### Latency & availability
- LLM calls are **slow and flaky** — every AI call is an async job (§7), never inline in
  a request. Target: pre-edit pack delivered ≤ 10s p95, with a loading state in the drawer.
- **AI down = invisible, never blocking.** Provider timeout/outage → the AI row hides or
  shows "AI unavailable" and manual work continues untouched. The kill switch is the same
  code path, flipped manually.
- Generation is idempotent per (story version, prompt version) — double-click ≠ double billing.

### Cost control (token budget)
- Per-call token estimate shown in the UI; **per-desk monthly caps** enforced
  server-side; alerts at 80% and 100% of cap. Cap hit → pre-edit pauses, manual work unaffected.
- Log tokens per call with desk + user attribution — the usage table in AI settings is
  read from this, per month.
- Cheaper model for routine tasks (tags, category), stronger model only for body rewrite
  and Bangla→English — route by task type.

### Safety & quality (the part that protects the brand)
- **New-facts detection server-side too:** diff AI output vs source for numbers, named
  entities and quotes; anything new is flagged `[VERIFY]` and counted in the publish gate.
  Client-side flagging (as prototyped) is UX; the server check is the guarantee.
- **Hallucination posture: AI never publishes facts the source didn't contain.** For
  routine auto-publish categories (weather, market close), input must be structured data
  feeds, not free text — numbers are templated, not generated.
- **Prompt injection is a real attack here:** raw material comes from the field,
  WhatsApp forwards, press releases — untrusted input. System prompt stays isolated,
  source text is delimited, and instructions inside source text are treated as data.
- Bangla→English translation quality gets the same spike treatment as search (§1):
  50 real field notes, desk editors grade the output, decide with data.

### Confidentiality (non-negotiable)
- **Unpublished, embargoed and exclusive stories leave the building only under a
  no-training / zero-retention agreement with the LLM provider** — an embargoed budget
  story in someone else's training data is a career-ending leak.
- If no provider agreement satisfies this → sensitive desks route to a self-hosted model,
  routine desks stay on the API. This decision is logged in §17 open questions until signed.

### Auto-publish guardrails (default OFF)
- Allowlist enforced **server-side** per category — the admin toggle is a UI over a
  server rule, never the rule itself.
- Rate-limited: max N auto-publishes/hour; exceeding it trips the kill switch
  automatically and alerts the desk (flood protection if a prompt goes rogue).
- Every auto-published story carries an "AI published" label internally, the full
  prompt/output pair in the audit log, and one-click rollback in the distribution log.

### Audit
- Every AI call logs: model + version, prompt version, input hash, output, who applied
  which suggestion, what the human changed afterwards. This folds into the legal trail
  in §8 — "the machine wrote it" is not a defense in a defamation case.
## 15. Shared utilities, service & business classes (recommended)

The editor, DAM, field apps, distribution and portal already share the same *concepts* —
the code should too. One shared core package consumed by the API **and** every worker,
so a rule (e.g. Bangla normalization) exists exactly once. Golden rule: **no business
logic in controllers/route handlers — services own it; workers call the same services.**

### Text & language
| Utility | Responsibility |
|---|---|
| `BanglaText` | Unicode NFC normalization, ০-৯ ↔ 0-9 digit conversion, conjunct-safe (যুক্তাক্ষর) truncation — runs on every ingest AND before indexing |
| `StemmerLight` | Bangla suffix-strip / variant expansion at index time (the ES-free plan B from §1) |
| `EnTagger` | background AI job — English search-only tags for Bangla stories; uploader confirms, never types (§1) |
| `WireText` | dateline parse/format, smart quotes & dash cleanup, word count / read time, END/UNB sign-off codes (the editor already implements these client-side — port to shared) |
| `Slugger` | Unicode-safe URL slugs for stories/photos |

### Search & entitlements
| Service | Responsibility |
|---|---|
| `SearchService` | single interface over Meilisearch (swap-able later); `search(index, query, filters)` |
| `EntitlementFilter` | builds the mandatory package filter per client — used identically by search, feed, and download endpoints |
| `IndexOutbox` | write-side contract: publish/edit/approve → outbox row → indexer |

### Media
| Service | Responsibility |
|---|---|
| `UploadSession` | tus resumable-upload wrapper, file-type sniffing, size limits |
| `DerivativeService` | thumb/preview/webp presets, EXIF handling (keep IPTC, strip GPS by policy) |
| `CaptionTemplate` | credit line ("Photo: X / UNB"), IPTC embed on export |
| `PresignedUrls` | time-boxed download links for ZIPs/originals |

### Delivery & messaging
| Service | Responsibility |
|---|---|
| `JobQueue` | `enqueue(type, payload, { idempotencyKey, delay, priority })` — the §7 house rule made concrete |
| `DeliveryClient` | per-channel sender (FTP / API push / webhook) with retry policy + receipt writing |
| `Mailer` | template render + queue enqueue — **never sends inline** in a request |
| `Notifier` | in-app + push fan-out (desk ← field events, approvals) |

### Platform
| Service | Responsibility |
|---|---|
| `TimeService` (aka DhakaClock) | Implements §11 — one clock for the whole system: storage/comparison in UTC (`nowUtc()`), staff display Asia/Dhaka, portal renders client-local, issue boundaries (09:00–08:59 Dhaka), embargo window math. Never call raw `now()` in business logic. |
| `AuditLog` | append-only (actor, action, entity, before/after hash) — the legal trail |
| `WorkflowSM` | story state machine: draft → in-review → changes-requested → approved → published; direct publish allowed per role/desk policy; ownership + shift-handover (`takeOver` force-releases edit lock, notifies predecessor); every transition permission-checked + audited |
| `NoteThread` | internal, immutable story notes (editor ⇄ sub-editor); posting fans out to the notification worker; never serialized to client APIs/feeds |
| `RevisionStore` | versioned content snapshots (editor revisions → server-side) + optimistic-lock version for concurrent-edit conflict detection (§8) |
| `Ids` | sortable IDs (ULID/UUIDv7) for stories, assets, batches, jobs |
| `RateLimiter` | per-client-API-key limits + counters |
| `CacheAside` | get-or-load with tag-based invalidation (feed, portal pages) |
| `CorrelationCtx` | trace ID that follows a story: field phone → desk → search index → client FTP |

### AI
| Service | Responsibility |
|---|---|
| `AIService` | single gateway for all LLM calls — model routing by task, timeout/retry, kill-switch check (§14) |
| `TokenMeter` | per-call token accounting, per-desk budget enforcement, 80%/100% alerts |
| `FactGuard` | diffs AI output vs source for new numbers/entities/quotes → `[VERIFY]` flags |
| `PromptGuard` | system-prompt isolation, untrusted source-text delimiting (injection defense) |

Test policy for this package: highest coverage in the codebase, and **every utility
touching Bangla text gets fixtures written in real Bangla**, not transliteration.

---

### 15.1 Domain business classes (suggested, per module)

Cross-cutting utilities live above; **these are the per-module domain classes** that
§16.1's service layer is built from. One class = one aggregate's business rules.
Key methods are the FRD's acceptance criteria turned into code — feature tests
target these. "Serves" cites FR IDs from `v1-functional-requirements.md`;
"Owns" cites tables from `v1-database-design.md`. Written in passes, like the FRD.

**Stories module** (`Modules/Stories`)

| Class | Responsibility & key methods | Serves | Owns |
|---|---|---|---|
| `StoryService` | draft lifecycle: `createDraft`, `updateDraft` (version check → 409), `getForEdit` (soft-lock state). Writes `story_versions` + `index_outbox` in one transaction | FR-NWS-001/002/005/014 | stories, story_versions |
| `PublishService` | `publish` (validates, sets `published_at` once, enqueues fan-out, audit), `quickPublish` (headline-only breaking), `unpublish`/`kill` (reason, kill notice) | FR-NWS-008/016 | stories, story_events, index_outbox |
| `ReviewService` | `sendToEditor`, `approve`, `requestChanges` — transition guards per role, notifies, writes chain-of-custody events | FR-NWS-011, FR-NWS-010 | stories, story_events |
| `HandoverService` | `takeOver(story, byUser)` — ownership transfer, force-release edit lock, system note, notify predecessor | FR-NWS-013 | stories, story_notes, story_events |
| `StoryNoteService` | `post` (immutable, internal-only), `thread` for drawer — never serialized client-side | FR-NWS-012 | story_notes |
| `MirrorService` | `linkMirror(en, bn)`, `syncStatus` hints between BN↔EN paired stories | FR-NWS-009 | stories.mirror_of_id |
| `EmbargoService` | `schedule` (explicit-tz input → UTC), `liftDue` (scheduler scan → release) — stories **and** embargoed media assets | FR-NWS-007, FR-MED-016, FR-DST-005 | stories.embargo_until, media_assets |
| `RevisionService` | version snapshots, `diff(v1,v2)`, `restore` | FR-NWS-003 (revision history) | story_versions |
| `DashboardService` | read-only KPI aggregation for the admin dashboard + content-pipeline counts; composes other services' read methods, never writes | FR-NWS-017/018, FR-FLD-010 | stories, deliveries, media_batches (read) |

**Media module** (`Modules/Media`)

| Class | Responsibility & key methods | Serves | Owns |
|---|---|---|---|
| `IntakeService` | batch assembly from completed tus sessions (`openBatch`, `attachUpload`, `submit`) — groups by uploader+event, sets server timestamps | FR-FLD-004/005/008, FR-MED-002 | media_batches, media_assets, upload_sessions |
| `MediaReviewService` | `approve`/`reject`/`requestReedit` per asset or per batch with reason codes; consequences: library move, urgent→Exclusive package, derivative kickoff, uploader notification | FR-MED-006/007 | media_assets, media_reviews, media_batches |
| `LibraryService` | inspector reads/writes: caption polish, credit template application, tags, metadata; inline-save audit | FR-MED-003/004 | media_assets |
| `DuplicateGuard` | checksum lookup on ingest → `check(file)` returns existing asset or null | FR-MED-010 | media_assets.checksum |
| `ApFeedImporter` | AP wire ingest worker-side: upsert read-only assets, license badge, never mixed into UNB library | FR-MED-014/015 | media_assets (source='ap') |
| `MediaDownloadService` | entitlement/permission check → presigned URL → ledger row | FR-MED-013, FR-PRT-005 | media_assets, downloads |

**Distribution module** (`Modules/Distribution`)

| Class | Responsibility & key methods | Serves | Owns |
|---|---|---|---|
| `PackageService` | package CRUD + subscription attach/detach (`attach(client, package, window)`); expiry sweep cuts delivery + portal at once | FR-DST-001/003 | packages, client_packages, package_media |
| `EntitlementResolver` | `effectiveFor(client)` = union of active packages; `compileMeiliFilter(client)` — the single source both delivery and tenant tokens compile from | FR-DST-002/003, FR-PRT-001 | packages, client_packages |
| `FanoutPlanner` | on publish/approve: `plan(deliverable)` → entitled client-channels, embargo hold, writes `deliveries` rows (queued) with idempotency keys | FR-DST-005 | deliveries, client_channels |
| `DeliveryService` | per-channel send via §15 `DeliveryClient`: `attempt(delivery)`, backoff, `autoPause` after N failures, payload-hash recording | FR-DST-006 | deliveries, client_channels |
| `ResendService` | manual resend from the log — reuses the same idempotency key, never duplicates | FR-DST-007 | deliveries |
| `KillNoticeService` | `correction(story)` / `kill(story, reason)` → update/kill notice fan-out to every client that received the original | FR-DST-008, FR-NWS-016 | deliveries, story_events |
| `ChannelService` | channel CRUD, vault-referenced secrets, `testConnection` before activation | FR-DST-004 | client_channels |
| `ServiceConfigService` | per-wire-service output formats, delivery windows, enablement | FR-DST-009 | settings |

**Clients module** (`Modules/Clients`)

| Class | Responsibility & key methods | Serves | Owns |
|---|---|---|---|
| `ClientService` | client CRUD; `suspend(client)` cascades: stop delivery + portal + API keys at once; `reactivate` | FR-CLT-001 | clients |
| `PortalAccountService` | `invite` (mail worker), `deactivate` (immediate session kill), client-role assignment | FR-CLT-002 | client_users |
| `ApiKeyService` | `issue` (raw shown once, hash stored), `rotate` (two keys overlap), `revoke`, rate-limit + scope config | FR-CLT-003 | client_api_keys |
| `ClientHealthService` | the 360 view: packages, channel health (last success / failure count), usage stats, recent delivery issues | FR-CLT-004 | clients, deliveries, downloads (read) |

**Portal module** (`Modules/Portal`)

| Class | Responsibility & key methods | Serves | Owns |
|---|---|---|---|
| `PortalFeedService` | home feed per entitlement: latest stories, category filter, breaking flags; CDN-cacheable responses | FR-PRT-002 | stories (read replica) |
| `TenantTokenIssuer` | short-lived Meilisearch tenant tokens with the compiled entitlement filter baked in — portal search never hits Laravel | FR-PRT-003 | (reads EntitlementResolver) |
| `FeedApiService` | `feed(since=cursor)` — cursor-paginated, UTC-only timestamps, per-key rate limit | FR-PRT-006 | stories, client_api_keys |
| `DownloadGateService` | entitlement check → presigned URL → downloads ledger (billing + audit) | FR-PRT-004/005 | downloads, stories, media_assets |
| `PortalAuthService` | client-user login, session/token issue, entitlement-scoped claims | FR-PRT-001 | client_users |

**Access module** (`Modules/Access`)

| Class | Responsibility & key methods | Serves | Owns |
|---|---|---|---|
| `RbacService` | role CRUD + permission matrix; `assertCan(user, module, action)` — the server-side scope check every endpoint runs; system-role lock, member-count guard on delete | FR-ACC-001/003 | roles, role_permissions |
| `StaffService` | `invite` (mail worker), `deactivate` — kills sessions + device tokens immediately, `reactivate` | FR-ACC-002 | users, devices |
| `DeviceRegistry` | field-device inventory: `register`, `heartbeat`, `revoke` (lost phone dies at once) | FR-FLD-009 | devices, personal_access_tokens |

**AI module** (`Modules/Ai`)

| Class | Responsibility & key methods | Serves | Owns |
|---|---|---|---|
| `PreeditOrchestrator` | the AI desk's server side: `run(kind, payload)` → job → §15 `AIService` → pack persisted on `ai_generations`; idempotent per story+prompt version | FR-AI-001/002/003 | ai_generations |
| `BudgetGuard` | `checkDesk(desk)` before every call: monthly cap from rollup, 80%/100% alerts; hard-block at cap | FR-AI-007, NFR §14 | ai_token_usage_daily, settings |
| `AutoPublishDecider` | `mayAutoPublish(story)`: kill switch off? category allowlisted? zero unreviewed markers? — decides gate-skip, always audits the decision | FR-AI-006/008 | settings, story_events |
| `KillSwitchService` | `engage`/`disengage` — forces auto-publish off, hides AI entry points, in-flight jobs finish, new jobs rejected; audited both ways | FR-AI-009 | settings |
| `EnTagService` | background English search-tags for Bangla content: `suggest(storyOrAsset)` → uploader `confirm(selection)` → index update via outbox | FR-AI-010 | stories, media_assets, index_outbox |

**Notifications module** (`Modules/Notifications`)

| Class | Responsibility & key methods | Serves | Owns |
|---|---|---|---|
| `EditorialNotifier` | workflow + intake events → recipient resolution (owner, assigned editor, desk, uploader) → in-app + email; burst-collapse per thread per 5 min | FR-NTF-001, FR-MED-006 | notifications |
| `NotificationCenterService` | unread counts, mark-read, deep links to story/batch/asset | FR-NTF-002 | notifications |
| `AuditQueryService` | per-entity audit history for admins ("who touched this story, end to end") | FR-NTF-003 | audit_logs (read) |

**Field module** (`Modules/Field`)

| Class | Responsibility & key methods | Serves | Owns |
|---|---|---|---|
| `AssignmentService` | desk pushes assignments (`create`, `assign`), field responds `accept`/`decline`, batches link back via `assignment_id`; status notifications both ways | FR-FLD-003 | assignments, media_batches |
| `FieldIngestService` | MoJo story filing → draft with source='mojo' + server-authoritative timestamps; never publishable from the field | FR-FLD-007/008 | stories |
| `OutboxSyncService` | field PWA outbox drain: ordered, idempotent re-submission of queued sends after reconnect | FR-FLD-001/002 | stories, media_batches |

---

**§15.1 status: complete — 8 modules, 48 domain classes.** Combined with the
cross-cutting utilities above, this is the full service-layer inventory for §16.1:
a thin controller or Livewire component should never need a class that is not
listed here. If one does, add the class here first, then write it.

## 16. v1 stack — decided

**Architecture: Laravel modular monolith** (API + admin + workers in one codebase),
with **one decoupled frontend: the client portal** (biggest consumer of news).

| Layer | Pick | Why |
|---|---|---|
| Backend | **Laravel** (modular monolith) | batteries included for exactly this workload; abundant local talent |
| Admin UI (editor, DAM, approval queue, RBAC, distribution) | **Blade + Livewire 3 + Alpine** (current HTML prototypes → Blade components + Livewire islands) | internal users, always online; no public admin API needed → smaller attack surface |
| **Client portal** | **Next.js (decoupled), hosted on CDN** | portal is the highest-traffic human surface; client experience = revenue. Cache: per-package-tier CDN keys, browser-direct Meilisearch, presigned downloads |
| Field apps (MoJo / photo desk) | **Static PWA** (the current HTML prototypes + service worker + IndexedDB outbox) | offline-first, <300 KB; Livewire impossible here (needs connectivity) |
| Client/field API | **Laravel REST API v1** (Sanctum tokens: field PWA + per-client scoped keys) + webhooks | only consumers that need it; admin never goes through it |
| Realtime (admin) | **Laravel Reverb** (websockets) | field events → live desk queue/badge updates |
| Queue & workers | **Redis queues + Horizon** (dedicated worker tier: mail, derivatives, indexing, fan-out…) | §7 — never inside request/response |
| Scheduler | **Laravel scheduler** (embargo lifts, archival sweep, digests, backup drills) | survives restarts, jobs idempotent |
| LLM provider | external API (task-routed models) via `AIService`; self-hosted fallback for sensitive desks | §14 — zero-retention agreement is a hard requirement |
| Primary DB | PostgreSQL (1 primary + 1 read replica) | relational core (stories, assets, clients, entitlements, audit) | **Full schema design: `v1-database-design.md`.**
| Search | Meilisearch — all surfaces incl. Bangla (spike-validated, stemmer-light fallback); `main` + `archive` indexes; **tenant tokens** for portal-direct queries | §1, §4 |
| Cache/hot feed | Redis | feed fan-out reads, rate-limit counters |
| Media | S3-compatible + CDN + tus resumable uploads | §3 |

**Contract that keeps this clean:** §15 services own all business logic; Livewire
components and API controllers are both thin callers. Never let a Livewire component
call the internal REST API (double hop, no benefit).

### 16.1 Application architecture — MVC + service + repository

The backend is organized in four strict layers, per module (stories, media,
distribution, clients, ai, access…):

1. **Controllers / Livewire components (thin).** HTTP only: validate input,
   authorize (scope check), call one service method, shape the response. No queries,
   no business rules, no model access beyond reading what the service returned.
2. **Services (the §15 list).** All business logic lives here: workflow transitions
   (`WorkflowSM`), publish fan-out, entitlement resolution, AI calls. Services own
   **transactions** — a content write and its `index_outbox` row commit together.
   Cross-module talk is service-to-service, never model-to-model across modules.
3. **Repositories (one per aggregate).** The ONLY place Eloquent/query-builder is
   used. They encapsulate: read-replica routing (reads hint the replica, writes the
   primary), partition-aware queries (`deliveries`, `audit_logs` always carry a date
   bound), eager-loading, and cursor pagination. Concrete classes — **no
   interface-per-model ceremony**; interfaces only where a real swap exists
   (`SearchService` → Meilisearch today, ES later; storage disk).
4. **Models (dumb).** Relations, casts, scopes, optimistic-lock increment. No
   business logic in models, observers, or "fat model" rescue methods.

Rules that keep this honest:
- Jobs/workers call **services**, never controllers; a job is just a deferred
  service call with an idempotency key.
- Caching lives in the service layer (`CacheAside`) — controllers never cache,
  repositories never cache.
- Blade/Livewire never query directly; everything on screen came through a service.
- The FRD's acceptance criteria map to **service methods**; the coding agent writes
  feature tests at the service layer, HTTP tests only for auth/validation.

## 17. Deferred (explicitly NOT v1)

- Elasticsearch/OpenSearch (deferred — budget; revisit only if the Bangla spike + stemmer-light genuinely fail)
- Multi-region active-active
- Video transcoding pipeline (v1: store + serve originals, external player)
- Ungated ML tagging/auto-captioning of photos — v1 allows AI *suggestions* with desk confirmation only (FR-MED-011, off by default per AI settings); fully automatic tagging is what's deferred
- Real-time push to portal (polling feed is fine for v1)
- AI auto-publish beyond allowlisted routine categories (weather / market / sports results only in v1)
- Public-facing website (UNB is B2B wire; portal serves clients only)

---

### Open questions to answer before v1 freeze

1. Real numbers: current story/photo volumes, client count, feed poll rates (replaces §0 guesses)
2. Bangla search spike result (§1) — biggest technical risk
3. Retention: confirm the 12-month hot window with the newsroom (§4); legal/counsel sign-off on 7-year audit retention
4. Any existing client FTP contracts with fixed delivery windows we must honor?
5. Hosting constraint: cloud allowed, or must run on UNB's own infra?
6. LLM provider data policy: who signs a no-training/zero-retention agreement? If none — which desks move to a self-hosted model? (§14)
