# UNB v1 — Functional Requirements (FRD)

**Companion docs:** `v1-non-functional-requirements.md` (engineering contract),
`v1-database-design.md` (schema), `README.md` (UI→framework conversion guide).
**Source of truth for behavior:** the prototype pages in this folder — each
requirement cites the page where the behavior is demonstrated.

## Writing conventions

- **ID:** `FR-<DOMAIN>-<nnn>` — stable, never reused. Domains: NWS (newsroom),
  AI (AI desk), MED (photos/media), FLD (field apps), DST (distribution),
  CLT (clients), PRT (client portal), ACC (access & settings), NTF (notifications/audit).
- **Priority (MoSCoW):** **M** = Must (v1 blocker), **S** = Should (v1 if no slip),
  **C** = Could (nice in v1).
- **Acceptance criteria** are testable bullets — QA and the coding agent both write
  tests from them.
- Anything marked *(prototype-only)* is demo scaffolding and must NOT be built.

## Personas

| Persona | Description | Main surfaces |
|---|---|---|
| Chief Editor (Nahar) | full access, final authority | all admin |
| Editor (Shohel, Ruma) | reviews desk copy, publishes, writes notes | news lists, story form |
| Sub-editor (Maria) | writes/edits copy, sends to editor or publishes directly if permitted | story form, lists |
| Uploader (desk) | ingests wire/partner content | story form, photo manager |
| Photojournalist / MoJo reporter (field) | shoots + uploads from the field, works offline | MoJo desk, Photo desk PWA |
| Photo desk editor | approves field batches into the library | UNB Photos intake queue |
| Client user | subscriber staff browsing/searching/downloading | client portal |
| Client system | automated feed poller (API/FTP) | API, FTP |
| Admin | roles, AI settings, kill switch | settings pages |

---

# Pass 1 — Newsroom & AI desk (complete)

## 1. Story authoring — add-news wizard
*Prototype: `add-news.html`*

### FR-NWS-001 — 4-step story wizard [M]
The story form is a wizard: **1 Write → 2 Media → 3 Organize & access → 4 Review &
publish**. Steps are clickable after being visited; "Continue" validates the current
step before advancing.
- Given an empty headline, when the user tries to leave step 1, then the headline
  field is marked invalid and the wizard stays on step 1.
- A stepper at the top shows progress; completed steps get a done state.

### FR-NWS-002 — Draft autosave & restore [M]
The form autosaves to draft storage debounced (every few seconds of typing).
On return to the page with a saved draft, a banner offers **Restore draft / Discard**.
- Publishing or discarding clears the draft.
- Manual "Save draft" gives explicit confirmation. *(Prototype uses
  `localStorage('unb_draft_v1')` — production: server-side drafts per user+story.)*

### FR-NWS-003 — Wire editor toolbar [M]
The body editor (Quill) carries a custom wire toolbar: dateline insert, pull quote,
table, UNB signoff, cleanup (strip junk formatting from pasted wire copy),
find & replace, and revision history.
- Dateline inserts the city + em-dash at cursor, formatted in Asia/Dhaka.
- Cleanup removes inline styles/scripts from pasted content, keeps semantic tags.
- Revision history lists saved versions and can restore one.

### FR-NWS-020 — Import from document [S]
"Import from doc" opens a modal that ingests a Word/text document (press releases,
filed copy) into headline/body, running cleanup automatically. The user reviews the
result before it lands in the form.

### FR-NWS-004 — Live preview [S]
A live preview panel renders headline/brief/body as the published story will look,
updating on input; it can collapse to a strip, expand fullscreen, and simulate
device widths.

### FR-NWS-005 — Brief & headline constraints [M]
Brief is capped at 280 characters with a live counter (wire-feed preview text).
Headline is required; category is required before publish.

### FR-NWS-006 — Media attachment [M]
Step 2 attaches photos/videos from the media library to the story: one featured
image plus inline/gallery items, each with an optional caption override.
- A story may publish without media; media can also be added **after** publish
  ("Add media now" on the success screen) and the update fans out to clients.

### FR-NWS-007 — Organization & access [M]
Step 3 sets category/subcategory, tags, language, priority (routine/urgent/flash),
embargo/scheduled publish time, and distribution preview (which packages will
receive it).
- Embargo input carries an explicit timezone (NFR §11) — stored UTC.

### FR-NWS-008 — Publish & quick publish [M]
"Publish story" on step 4 and "Publish now" (any step, breaking news) both publish.
Quick publish requires only a headline.
- On publish: story enters the distribution queue; success screen offers view-in-list
  / add-another / add-media.
- Publish sets `published_at` once (UTC, server-set); re-edits don't change it.

### FR-NWS-009 — Bangla desk mirrors English [M]
The Bangla news desk is a functional mirror of the English desk: same wizard,
workflow, AI desk and list behaviors, with Bangla UI text, Bangla typography, and
Bangla category names. A story can be linked to its mirror (`mirror_of_id`).
*(Prototype: mirror `english-news.html` / `add-news.html` — Bangla pages pending.)*

## 2. Editorial workflow
*Prototype: `add-news.html` workflow strip, `english-news.html` workflow drawer*

### FR-NWS-010 — Workflow states [M]
A story moves: **draft → in_review → changes_requested → approved → published**
(plus `killed`, `archived`). Sub-editors with publish permission may publish
directly from draft; others must route through an editor.
- Every transition is permission-checked server-side and audit-logged
  (who/when/from→to) — NFR §8.

### FR-NWS-011 — Send to editor [M]
The workflow strip offers **Send to editor**: the story moves to in_review, is
assigned to the desk editor, and the editor is notified. A confirmation screen
explains the next states (approved / published / sent back with notes).

### FR-NWS-012 — Internal notes thread [M]
Each story carries a newsroom-only notes thread. Editors and sub-editors post notes;
anyone on the desk can reply. Notes are immutable once posted.
- Notes are never published, never sent to clients, never exposed on client APIs,
  and never indexed into client-reachable search (NFR §8).
- System events (sent-to-review, handover, publish) appear inline in the thread.

### FR-NWS-013 — Ownership & shift handover [M]
Every story has one owner (a sub-editor). Another staff member with edit permission
can **Take over** — ownership transfers, the previous owner is notified, the edit
lock is force-released, and the handover is recorded as a chain-of-custody event
in both the notes thread and the audit log.

### FR-NWS-014 — Concurrent editing protection [M]
Opening a story someone else is editing shows a soft lock ("Maria is editing now").
Saves carry a version number; a stale save is rejected (409) with a merge/reload
prompt — never a silent overwrite (NFR §8).

### FR-NWS-015 — Workflow visibility in lists [M]
The news list shows workflow status pills (In review, Needs work), the owner line,
and an internal-notes badge with count. Clicking the badge opens the **workflow
drawer**: status flow, owner card with Take over, and the full notes thread with
reply — without leaving the list.
- The status filter includes In review / Needs work.

### FR-NWS-016 — Live toggle / unpublish [M]
Published stories can be taken offline from the list (Live toggle off). Unpublishing
is audited and triggers a correction/kill notice to clients per distribution rules.

## 2.5 Newsroom surfaces
*Prototype: `index.html`, `story.html`; content pipeline — sidebar link with badge,
page pending design*

### FR-NWS-017 — Dashboard [M]
The admin dashboard shows operational KPIs — stories published today, active
clients, distribution success rate, exclusive content sent — plus recent activity
and actionable alerts (field intake waiting, failed deliveries, AI budget warnings).
Every panel deep-links to its working surface.

### FR-NWS-018 — Content pipeline [M]
A pipeline view shows all in-flight stories grouped by workflow status
(draft / in review / changes requested / approved), with per-column counts and the
owner on each card — the desk's answer to "what is stuck where, with whom."
Clicking a card opens the story form. *(Sidebar badge shows the actionable count.)*

### FR-NWS-019 — Story detail view (admin) [S]
A read view of one story: full rendered copy, metadata, workflow state, version
history, internal notes and audit history, with actions appropriate to role
(edit / send to editor / unpublish). Restyle `story.html` to current chrome.

## 3. AI desk
*Prototype: `add-news.html` AI drawer + compare modal, `ai-settings.html`*

### FR-AI-001 — Start with AI (raw → draft) [M]
The user pastes raw material (field notes, press release, WhatsApp forward —
English or Bangla) and AI drafts headline, brief, body, category and tags.
Bangla input is auto-translated to English wire copy per the house style prompt.
- Nothing is applied without explicit per-card user action.

### FR-AI-002 — Pre-edit suggestions drawer [M]
"Pre-edit draft", "Tags & category", and "BN→EN" actions call the AI and open a
suggestion drawer: headline variants with rationale, polished body, category with
confidence + why, tag chips. Each card has **Use**; unused suggestions are discarded.
- The drawer shows session token usage.

### FR-AI-003 — Raw vs AI compare [S]
Before applying a body rewrite or translation, the user can open a side-by-side
compare view (raw vs AI) — word-level diff for same-language rewrites, plain
panes for cross-language. Apply happens from the compare modal.

### FR-AI-004 — AI-touched markers [M]
Any field populated from AI gets an "✦ AI — unreviewed" marker. A human edit clears
the marker for that field. Markers drive the publish gate.

### FR-AI-005 — New-facts warning [M]
If the AI output introduces facts not present in the source (numbers, names, quotes),
the drawer flags them prominently ("N new facts — verify before use").

### FR-AI-006 — Publish gate for AI-assisted stories [M]
Publishing a story with any unreviewed AI-touched field requires a checklist gate
(facts verified / style checked / new facts checked). The gate is skipped only when
auto-publish is on AND the category is allowlisted (FR-AI-008).

### FR-AI-007 — AI settings per desk [M]
Admins configure per-desk pre-edit enablement (English, Bangla, Photos), the house
style prompt, and the monthly token budget with per-desk usage bars. Settings
persist server-side and are read by the story form at load.
*(Prototype: `localStorage('unb_ai_settings')` — production: `settings` table.)*

### FR-AI-008 — Auto-publish (default OFF) [M]
Auto-publish is a danger-zone toggle requiring modal confirmation. When on, only
allowlisted routine categories (weather, sports results, market close, currency
rates) may auto-publish; every auto-publish is audit-logged as such.

### FR-AI-009 — Kill switch [M]
A global kill switch instantly disables all AI features newsroom-wide: AI buttons
hide, an explanatory note shows on the story form, in-flight jobs complete but no
new ones are accepted, and auto-publish is forced off. Kill-switch flips are audited.

### FR-AI-010 — AI English search tags [S]
Bangla stories get AI-generated English search-only tags in the background
(NFR §1 layer 3); the uploader confirms suggested tags — they never type them.
These tags feed search indexes only, never display.

---

# Pass 2 — Media & Field apps (complete)

## 4. Media library & desk review
*Prototype: `unb-photo-manager.html`*

### FR-MED-001 — Library grid [M]
Photos/video display in a justified photo grid with fast-loading derivative
thumbnails; selecting opens the inspector panel. Grid holds up at thousands of
items via pagination/infinite scroll (search itself is Meilisearch — NFR §1).

### FR-MED-002 — Workflow tabs with counts [M]
Tabs segment the library by status — **Field intake**, pending, library, rejected —
each with a live count badge; the field-intake tab alerts on new batches.
- Field-intake items never appear in the "all"/library views until approved.

### FR-MED-003 — Inspector panel [M]
A side panel shows the selected asset: preview, caption, credit line, event,
location, category, tags, EXIF/capture data, upload source and review history.
All editable fields save inline and are audited.

### FR-MED-004 — Caption, credit & metadata editing [M]
Desk editors polish field captions and metadata before an asset is usable.
Credit line follows the house template ("Photo: {Name} / UNB") — pre-filled from
the uploader, editable by the desk (NFR §15 CaptionTemplate).

### FR-MED-005 — Selection & bulk actions [M]
Hover-reveal selection, a bulk action bar (approve / reject / add to package /
download ZIP), and shift-range selection.
- ZIP export is an async job with a download link when ready — never synchronous
  (NFR §2/§7).

### FR-MED-006 — Field intake approval queue [M]
Incoming field uploads land as **batches grouped by uploader + event**, showing
urgency, wait time and shot count. The desk can:
- approve/reject **per photo**, or approve-all / reject / request-re-edit **per batch**;
- reject and re-edit require a reason — preset reason list + free note in a modal.
- Decisions notify the uploader (FR-NTF); approved assets move to the library.

### FR-MED-007 — Approval consequences [M]
On approve: status → library, derivatives confirmed ready, asset becomes searchable
and attachable to stories. Urgent batches additionally land in the Exclusive package
for clients (per package rules, FR-DST).
- Reject → status rejected + reason; re-edit → status reedit + note back to the
  photojournalist's field app.

### FR-MED-016 — Media embargo [M]
Assets can carry an embargo ("hold until 6:00 PM") — visible as an embargo status
with the lift time; they are withheld from packages/portal until the embargo lifts,
then release automatically (explicit-tz input, UTC compare — NFR §11).

### FR-MED-008 — Derivative generation [M]
Every accepted original gets derivatives (thumb/small/large/webp; HLS for video)
generated by a worker (NFR §3/§7). Derivative URLs are immutable; originals stay
in cold storage.

### FR-MED-009 — Desk upload [M]
Desk staff upload photos/videos directly (not via field app) through the same
resumable-upload path, landing directly in pending review (not field intake).

### FR-MED-010 — Duplicate detection [S]
Checksum (sha256) on ingest; a duplicate warns the desk with a link to the
existing asset instead of silently storing twice.

### FR-MED-011 — AI captions & tags for photos [S]
When enabled per-desk (FR-AI-007, Photos toggle), AI suggests captions and English
search tags on intake; the desk confirms before anything is saved. Bangla-captioned
assets get English search-only tags (FR-AI-010).

### FR-MED-012 — Media search [M]
Staff search the library by caption, event, tag, location, credit — served by the
Meilisearch media index (NFR §1), including the Bangla-search mitigations
(stemmer-light, synonyms, en_tags).

### FR-MED-013 — Download & tracking [M]
Staff download originals/derivatives via short-lived presigned URLs; every
download is logged (who, what, when — `downloads` ledger).

## 5. AP wire photos
*Prototype: `ap-photo-manager.html`*

### FR-MED-017 — Per-asset download analytics [S]
Each asset shows download count and top downloading clients — the desk sees which
photos clients actually use, informing what to shoot more of.

### FR-MED-014 — AP feed browsing [M]
AP (wire) photos arrive via the partner feed and are browsable/searchable in a
separate manager — clearly badged as wire content, never mixed into the UNB library.

### FR-MED-015 — AP usage [M]
Desk staff attach AP photos to stories or download them per the wire license;
usage is logged. AP assets are read-only in the inspector (no caption editing —
license requirement).

## 6. Field apps (MoJo desk + Photo desk)
*Prototype: `mojo-field-desk.html`, `photo-field-desk.html` — static PWA, 680px
phone-first shell with bottom tabs; NFR §9*

### FR-FLD-001 — Offline-first PWA [M]
Both field apps are installable PWAs that work fully offline: capture, caption and
queue without connectivity; everything syncs when back online.
- A persistent online/offline indicator + toggle; queued items show a pending count.

### FR-FLD-002 — Send queue [M]
Failed/offline sends land in a visible outbox queue with per-item retry; the queue
drains automatically on reconnect, oldest first. Nothing is lost on app kill.

### FR-FLD-003 — Assignments [M]
Field staff see assignments pushed by the desk (title, shot list, location, due time,
priority), and can **Accept / Can't do it / Upload frames for this**.
- Accept/decline notifies the desk; assignments link uploaded batches to the story
  context.

### FR-FLD-004 — Batch photo upload [M]
Photo desk flow: multi-frame capture/pick, per-frame captions, batch event label,
urgency, location (datalist of districts), auto credit line from the profile.
One-tap "caption all" applies a base caption to every frame for later per-frame edits.
- *(Prototype-only: "Add 3 demo frames" button — do not build.)*

### FR-FLD-005 — Resumable upload [M]
Uploads use resumable sessions (tus): interrupted uploads resume from the last byte
after signal loss/app restart (NFR §3). Progress is per-batch and per-frame.

### FR-FLD-006 — Submission status tracking [M]
Field staff track each batch: pending → approved / re-edit / rejected, with the
desk's reason note visible. Re-edit items offer **Fix & resend** (reopens the batch
with notes); rejected items can be archived locally.

### FR-FLD-007 — MoJo story filing [M]
MoJo reporters file raw story copy (text, photos, voice notes metadata) from the
field; it lands in the newsroom as a draft with source='mojo' for a sub-editor to
pick up — field staff never publish directly.

### FR-FLD-008 — Server-authoritative time [M]
All ingest timestamps are set server-side; device time is display-only
(NFR §11 — dead batteries and manual clock changes must not corrupt the record).

### FR-FLD-009 — Device binding [M]
Field apps authenticate with device-bound, revocable tokens (NFR §8); a lost phone
is revoked from the admin and immediately loses access. The app shows the signed-in
identity and desk at all times.

### FR-FLD-010 — Field stats & profile [C]
Home screen shows the journalist's own stats (sent/approved/rejected/downloads)
and active assignment banner — motivation + self-service status.

---

# Pass 3 — Distribution, Clients, Portal, Access, Notifications (complete)

## 7. Distribution
*Prototype: `packages.html`, `delivery-settings.html`, `english-service.html`,
distribution log (sidebar); semantics in NFR §6*

### FR-DST-001 — Package management [M]
Packages are managed with a kind (news / photos / bundle), description, price and
status. Package codes are stable identifiers used in feeds and FTP paths.

### FR-DST-002 — Entitlement definition [M]
Each package declares an entitlement filter: languages, categories (or all), media
kinds. **One definition drives everything** — delivery fan-out, portal visibility,
and the Meilisearch tenant-token filter all compile from the same source, so a
client can never see in search what delivery would not send (NFR §1).

### FR-DST-003 — Client subscriptions [M]
Clients are attached to packages with start/end dates; overlapping subscriptions
union their entitlements. Expiry automatically cuts both delivery and portal access.

### FR-DST-004 — Delivery channels [M]
Each client configures one or more channels: **API** (feed polling), **FTP** (wire
files pushed to the client's path), **webhook** (push on publish). Channel config
stores secrets as vault references only (NFR §8). A "test connection" action
verifies the channel before activation.

### FR-DST-005 — Publish fan-out [M]
Publishing a story (or approving media into a package) enqueues a delivery per
entitled client-channel as an async job — never inline in the publish request
(NFR §7). Embargoed content queues but holds until `embargo_until`, then fans out
automatically.

### FR-DST-006 — Delivery guarantees [M]
At-least-once delivery with idempotency keys — retries never double-deliver.
Exponential backoff; after N consecutive failures a channel auto-pauses and the
client contact is notified. Every delivery records a payload hash proving exactly
which text went out (NFR §8).

### FR-DST-007 — Distribution log [M]
A filterable log (client, channel, status, date range) shows every delivery with
attempts, response codes and errors. Failed deliveries can be **resent** manually
(same idempotency key — safe). Old partitions archive per NFR §4.

### FR-DST-008 — Corrections & kill notices [M]
Editing a published story fans out an **update**; unpublishing/killing fans out a
**kill notice** to every client that received it. Kills are audited with reason.

### FR-DST-009 — Service configuration [S]
Wire services (English/Bangla service pages) configure output formats, delivery
windows and per-service enablement — restyle to current chrome, behavior unchanged.

## 8. Clients
*Prototype: `clients.html`*

### FR-CLT-001 — Client management [M]
CRUD for subscriber organizations: name, code, type, country, timezone, status,
billing contact. Suspension stops delivery + portal + API access immediately.

### FR-CLT-002 — Portal user management [M]
Per client: invite portal users, assign a client role, deactivate. Invitations are
emailed (dedicated mail worker — NFR §7); deactivated users lose access at once.

### FR-CLT-003 — API keys [M]
Issue scoped, rate-limited API keys per client; rotate and revoke without downtime
(two active keys during rotation). Raw key is shown once — only the hash is stored.

### FR-CLT-004 — Client 360 [S]
The client page shows packages, channels with health (last success, failure count),
download/usage stats and recent delivery issues — support staff answer "did you get
story X?" without touching the database.

## 9. Client portal
*Prototype: `client-portal.html` — Next.js, decoupled, CDN-hosted; the biggest
consumer of news (NFR §0/§16)*

### FR-PRT-001 — Portal login [M]
Client users log in with their portal account; everything they see is scoped by
their client's active entitlements.

### FR-PRT-002 — Home feed [M]
The portal home shows the latest stories the client is entitled to, with category
filtering, breaking/urgent flags, and story detail view (headline, brief, body,
dateline, media, credit).

### FR-PRT-003 — Superfast search [M]
Full-text search over thousands of stories/photos/videos with filters (category,
date range, language, media kind) — served browser-direct by Meilisearch with a
tenant token carrying the entitlement filter (NFR §1). Results include the archive
index (>12 months) clearly labeled as archive.

### FR-PRT-004 — Story download [M]
Clients download stories as text/XML (wire format) — per-package entitlement
checked server-side before issuing the file.

### FR-PRT-005 — Media download [M]
Entitled photos/videos download as derivatives or originals via short-lived
presigned URLs; every download lands in the downloads ledger (billing + audit).

### FR-PRT-006 — Feed API [M]
Programmatic clients poll `GET /api/v1/feed?since=<cursor>` — cacheable, cursor-
paginated, rate-limited per API key. Responses carry UTC ISO timestamps only
(NFR §11).

### FR-PRT-007 — Local-time rendering [M]
The portal renders all times in the consumer's timezone (browser IANA), with the
original Dhaka time as secondary label (NFR §11).

### FR-PRT-008 — Bangla content [M]
Bangla-service clients get the same portal experience over Bangla content —
Bangla typography, Bangla UI labels — per their package.

## 10. Access & settings
*Prototype: `roles.html`, `ai-settings.html`, preferences*

### FR-ACC-001 — Role management [M]
Custom roles are created/edited with a per-module permission matrix
(view/create/edit/publish/delete). System roles are locked against edit/delete.
Deleting a role with members is blocked until members are reassigned.

### FR-ACC-002 — Staff management [M]
Invite staff with a role and desk, deactivate, reactivate. Deactivation kills
sessions and device tokens immediately.

### FR-ACC-003 — Permission enforcement [M]
Every admin surface hides what the role can't do — and every API endpoint enforces
the same scopes server-side (NFR §8). Client roles control portal views only.

### FR-ACC-005 — Preferences [C]
Per-user display preferences (timezone display, date format, density). Never
overrides server truth — presentation only (NFR §11).

### FR-ACC-004 — AI administration [M]
Covered by FR-AI-007/008/009: per-desk toggles, token budget, style prompt,
auto-publish allowlist, kill switch.

## 11. Notifications & audit

### FR-NTF-001 — Editorial notifications [M]
In-app + email notifications for: review requested, note added/replied, approved /
changes-requested, handover (to both parties), field-batch decisions (to the
photojournalist). Delivered by the notification worker, burst-collapsed per thread
per 5 minutes (NFR §7).

### FR-NTF-002 — Notification center [M]
The topnav bell shows unread notifications with badge count; clicking one lands on
the relevant story/batch/asset.

### FR-NTF-003 — Audit trail [M]
Every sensitive action — story transitions, notes, handovers, publishes, kills,
role changes, API key issues, AI gate bypasses, kill-switch flips — lands in the
append-only audit log with actor, timestamp and before/after diff (NFR §8).
Per-entity audit history is viewable by admins.

---

## v1 boundary (out of scope)

Per NFR §17: Elasticsearch, billing/invoicing automation, multi-region deployment,
admin mobile apps, client self-serve subscription changes. Anything not listed in
this FRD's domains is not v1 scope.

---

**Status: all three passes complete + review round 1.** Domains: NWS ✓ AI ✓ MED ✓
FLD ✓ DST ✓ CLT ✓ PRT ✓ ACC ✓ NTF ✓ — 86 requirements with acceptance criteria.

---

## Appendix A — Traceability: FR → business classes (NFR §15.1)

Test-planning map: every FR names its **primary** class (write feature tests here)
and **supporting** pieces (cross-cutting §15 utilities or other modules' services).
"UI-only" = client-side behavior with no new server logic.

| FR | Primary class | Supporting |
|---|---|---|
| FR-NWS-001 wizard | `StoryService` (createDraft/updateDraft) | — |
| FR-NWS-002 drafts | `StoryService` | — |
| FR-NWS-003 wire toolbar | `RevisionService` (history) | `WireText` (cleanup) |
| FR-NWS-004 live preview | UI-only | — |
| FR-NWS-005 constraints | `StoryService` (validation) | — |
| FR-NWS-006 media attach | `StoryService` (story_media) | `LibraryService` |
| FR-NWS-007 organize/embargo | `EmbargoService` | `StoryService` |
| FR-NWS-008 publish | `PublishService` | `JobQueue`, `AuditLog` |
| FR-NWS-009 Bangla mirror | `MirrorService` | `BanglaText` |
| FR-NWS-010 states | `ReviewService` | `WorkflowSM` |
| FR-NWS-011 send to editor | `ReviewService` | `EditorialNotifier` |
| FR-NWS-012 notes | `StoryNoteService` | `NoteThread` |
| FR-NWS-013 handover | `HandoverService` | `EditorialNotifier`, `AuditLog` |
| FR-NWS-014 concurrency | `StoryService` (version/lock) | `RevisionStore` |
| FR-NWS-015 list visibility | `StoryService` (list reads) | UI drawer |
| FR-NWS-016 unpublish | `PublishService.unpublish` | `KillNoticeService` |
| FR-NWS-017 dashboard | `DashboardService` | `CacheAside` |
| FR-NWS-018 pipeline | `DashboardService` | `StoryService` |
| FR-NWS-019 story view | `StoryService` | `RevisionService`, `StoryNoteService`, `AuditQueryService` |
| FR-NWS-020 import doc | `StoryService` | `WireText` |
| FR-AI-001 start with AI | `PreeditOrchestrator` | `AIService`, `PromptGuard` |
| FR-AI-002 drawer | `PreeditOrchestrator` | `AIService` |
| FR-AI-003 compare | UI-only | `PreeditOrchestrator` (stored pack) |
| FR-AI-004 markers | `StoryService` (`ai_touched`) | UI markers |
| FR-AI-005 new facts | `PreeditOrchestrator` | `FactGuard` |
| FR-AI-006 publish gate | `AutoPublishDecider` | `PublishService` |
| FR-AI-007 AI settings | `BudgetGuard` | `KillSwitchService` (reads same settings) |
| FR-AI-008 auto-publish | `AutoPublishDecider` | `PublishService`, `AuditLog` |
| FR-AI-009 kill switch | `KillSwitchService` | `AIService` (checks it) |
| FR-AI-010 en-tags | `EnTagService` | `AIService`, `IndexOutbox` |
| FR-MED-001 grid | `LibraryService` | `SearchService` |
| FR-MED-002 tabs/counts | `IntakeService` | `LibraryService` |
| FR-MED-003 inspector | `LibraryService` | — |
| FR-MED-004 caption/credit | `LibraryService` | `CaptionTemplate` |
| FR-MED-005 bulk actions | `MediaReviewService` | `MediaDownloadService` (ZIP job) |
| FR-MED-006 intake queue | `MediaReviewService` | `IntakeService`, `EditorialNotifier` |
| FR-MED-007 approval consequences | `MediaReviewService` | `FanoutPlanner`, `DerivativeService` |
| FR-MED-008 derivatives | `LibraryService` (reads manifest) | `DerivativeService` |
| FR-MED-009 desk upload | `IntakeService` (desk lane) | `UploadSession` |
| FR-MED-010 duplicates | `DuplicateGuard` | — |
| FR-MED-011 AI captions | `PreeditOrchestrator` (photos) | `EnTagService` |
| FR-MED-012 media search | `LibraryService` | `SearchService`, `StemmerLight`, `EnTagger` |
| FR-MED-013 downloads | `MediaDownloadService` | `PresignedUrls` |
| FR-MED-014 AP browse | `ApFeedImporter` | `SearchService` |
| FR-MED-015 AP usage | `ApFeedImporter` | `MediaDownloadService` |
| FR-MED-016 media embargo | `EmbargoService` | `TimeService` |
| FR-MED-017 download analytics | `MediaDownloadService` (ledger reads) | — |
| FR-FLD-001 offline PWA | UI-only (PWA shell) | `OutboxSyncService` |
| FR-FLD-002 send queue | `OutboxSyncService` | `JobQueue` |
| FR-FLD-003 assignments | `AssignmentService` | `EditorialNotifier` |
| FR-FLD-004 batch upload | `IntakeService` | `UploadSession`, `CaptionTemplate` |
| FR-FLD-005 resumable | `IntakeService` | `UploadSession` (tus) |
| FR-FLD-006 status tracking | `MediaReviewService` (decision source) | `EditorialNotifier` |
| FR-FLD-007 MoJo filing | `FieldIngestService` | `StoryService` |
| FR-FLD-008 server time | `FieldIngestService` | `TimeService` |
| FR-FLD-009 device binding | `DeviceRegistry` | `RateLimiter` |
| FR-FLD-010 field stats | `DashboardService` (per-user) | `IntakeService` |
| FR-DST-001 packages | `PackageService` | — |
| FR-DST-002 entitlement | `EntitlementResolver` | `PackageService` |
| FR-DST-003 subscriptions | `PackageService` (attach/detach) | `EntitlementResolver` |
| FR-DST-004 channels | `ChannelService` | — |
| FR-DST-005 fan-out | `FanoutPlanner` | `EmbargoService`, `JobQueue` |
| FR-DST-006 guarantees | `DeliveryService` | `DeliveryClient`, `Ids` (idempotency) |
| FR-DST-007 log | `DeliveryService` (reads) | `ResendService` |
| FR-DST-008 kill/correction | `KillNoticeService` | `FanoutPlanner` |
| FR-DST-009 service config | `ServiceConfigService` | — |
| FR-CLT-001 client mgmt | `ClientService` | — |
| FR-CLT-002 portal users | `PortalAccountService` | `Mailer` |
| FR-CLT-003 API keys | `ApiKeyService` | `RateLimiter` |
| FR-CLT-004 client 360 | `ClientHealthService` | — |
| FR-PRT-001 login | `PortalAuthService` | `EntitlementResolver` |
| FR-PRT-002 home feed | `PortalFeedService` | `CacheAside` |
| FR-PRT-003 search | `TenantTokenIssuer` | Meilisearch (browser-direct) |
| FR-PRT-004 story download | `DownloadGateService` | — |
| FR-PRT-005 media download | `DownloadGateService` | `PresignedUrls`, `MediaDownloadService` |
| FR-PRT-006 feed API | `FeedApiService` | `RateLimiter`, `TimeService` |
| FR-PRT-007 local time | UI-only | `TimeService` (API payload shape) |
| FR-PRT-008 Bangla portal | `PortalFeedService` | `TenantTokenIssuer`, `BanglaText` |
| FR-ACC-001 roles | `RbacService` | `AuditLog` |
| FR-ACC-002 staff | `StaffService` | `Mailer`, `DeviceRegistry` |
| FR-ACC-003 enforcement | `RbacService.assertCan` | — |
| FR-ACC-004 AI admin | `BudgetGuard` | `KillSwitchService`, `AutoPublishDecider` |
| FR-ACC-005 preferences | `StaffService` (profile prefs) | UI-only presentation |
| FR-NTF-001 editorial notif. | `EditorialNotifier` | `Notifier`, `Mailer` |
| FR-NTF-002 notif. center | `NotificationCenterService` | — |
| FR-NTF-003 audit | `AuditQueryService` | `AuditLog` |

**Coverage check:** all 86 FRs map to at least one class; every §15.1 class serves
at least one FR. Two classes were added during review for full coverage:
`PackageService` and `DashboardService`.



