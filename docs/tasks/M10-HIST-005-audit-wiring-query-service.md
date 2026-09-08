# Task: M10-HIST-005 — Sensitive actions → `audit_logs` + `AuditQueryService`

**Status:** ⏳ Pending
**Dependencies:** M10-HIST-001, M10-HIST-004
**Parent ADR:** FR-NTF-003 (audit trail), NFR §15 (`AuditQueryService`), `docs/plans/history-audit-design.md` §2

---

## 1. Contract (What)
- **Inputs / Validation:** FR-NTF-003 action list fully wired to `audit_logs`: story transitions, publishes, kills, notes, handovers (StoryService/NoteService — new); role changes, user mgmt (RolesManager — exists, enrich); API key issues/rotations (ApiKeyService — new); AI gate bypasses + kill-switch flips (AiSettings — exists, enrich).
- **Outputs / Response:** `AuditLogRepository::log()` extended signature: `actor, action, entityType, entityId, diff: array, ip: ?string, userAgent: ?string, correlationId: ?string`. `correlation_id` = `X-Correlation-Id` request header ?? `Str::uuid()`. `AuditQueryService::forEntity(string $type, int|string $id): LengthAwarePaginator` + `search(actorId, action, entityType, dateFrom, dateTo): LengthAwarePaginator`.
- **Authorization:** Read paths gated `audit.can_view` at controller/component layer (M10-HIST-009 consumes).

---

## 2. Logic (How)
1. Extend `AuditLogRepository::log()` with context columns; keep backward compatibility with existing callers (default nulls).
2. Wire story actions: `StoryService::transition/takeOver` + `NoteService::add` + `RevisionService::restore` write audit rows (actor, before/after `diff`, ip/ua/correlation) **in the same transaction**.
3. Wire `ApiKeyService` issue/rotate; enrich RolesManager + AiSettings existing writes to route through the extended `log()` (captures ip/ua/correlation now).
4. `RoleRepository`'s duplicate `logAudit()/getRecentAudits()` delegate to `AuditLogRepository` (no behavior change — dedupe flagged in gap analysis).
5. Create `AuditQueryService` (read-only, eager actor, paginated).
6. Append-only: no update/delete path exists — do not add one.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Repositories/AuditLogRepository.php`, `app/Repositories/RoleRepository.php`
  - `app/Services/AuditQueryService.php` (new)
  - `app/Services/StoryService.php`, `app/Services/NoteService.php`, `app/Services/RevisionService.php`, `app/Services/ApiKeyService.php`
  - `app/Livewire/Admin/RolesManager.php`, `app/Livewire/Admin/AiSettings.php`
  - `tests/Feature/AuditLogTest.php` (new/extend)
- **Reference Files:** `app-data/v1-database-design.md` `audit_logs` §, FR-NTF-003

---

## 4. Prompt (For the Coding AI)
> Extend AuditLogRepository::log() with ip/user_agent/correlation_id (X-Correlation-Id ?? Str::uuid()) + diff, backward compatible. Wire audit writes (same transaction) into StoryService transition/takeOver, NoteService add, RevisionService restore, ApiKeyService issue/rotate; route RolesManager + AiSettings existing inline writes through the repo method. RoleRepository audit methods delegate to AuditLogRepository. Create AuditQueryService: forEntity(type,id) + search(filters) paginated, no writes. Tests: publish writes audit row with actor+diff+ip+correlation; API key rotation audited; forEntity returns only that story's rows; no update/delete path.

---

## 5. Test Criteria
- [ ] Publish/kill/restore/handover/note each produce an `audit_logs` row with actor, diff, ip, correlation_id
- [ ] API key issue + rotation audited (FR-NTF-003)
- [ ] `forEntity('Story', X)` returns only story X rows, paginated, actor eager-loaded
- [ ] RolesManager "Activity" tab still renders (enriched rows)
- [ ] Transactionality: audit row absent when action transaction rolls back
- [ ] `php -l` clean; no N+1 in query service

---

## 6. Completion Notes
- **Shipped:** —
- **Tests:** —
- **Live Smoke:** —
- **Review:** —

---

## 7. Prompt Ready?
- [x] Yes
