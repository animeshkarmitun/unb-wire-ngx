# Task: COS-68/67/70/69/71 — Audit log batch: traceability + readability

**Status:** 🔧 In Progress
**Priority:** P2 (COS-68) + P3/P4 companions — one combined fix
**Linear:** [COS-68](https://linear.app/cosmosgroupcom/issue/COS-68/audit-role-and-user-changes-are-logged-without-ip-or) · COS-67 · COS-70 · COS-69 · COS-71
**Affected surfaces:** RolesManager, AiSettings, AuditLogRepository, AuditLogBrowser (+ view), sidebar unchanged
**Parent contracts:** M10-HIST-004/005 (event vocabulary, correlation semantics)

---

## 1. Issues

| ID | Symptom | Root cause |
|---|---|---|
| COS-68 | role/user actions logged without IP or correlation ID | 9 inline `DB::table('audit_logs')->insert()` sites in RolesManager (+2 in AiSettings) bypassed `AuditLogRepository::log()` (which auto-captures ip/ua/correlation) |
| COS-67 | Actor column shows "User #1" | Browser printed raw `actor_id` |
| COS-70 | user.invited/deactivated "no entity ID" | DB rows had entity_id; the RolesManager Activity panel only showed the diff message; browser filters lacked the actions |
| COS-69 | Diff column shows HTML + styling metadata (`<b>`, `var(--crimson)`) | diffs were stored as HTML marketing strings; browser dumped raw JSON |
| COS-71 | Same-minute entries out of order, timestamps without seconds | `orderByDesc('created_at')` with no `id` tiebreaker; display formats have no seconds |

## 2. Fix

- **RolesManager**: all 9 inline inserts → private `audit()` helper → `AuditLogRepository::log()` (ip/user-agent/correlation captured); diffs now **plain readable messages** (no HTML/color).
- **AiSettings**: both inline `AuditLog::create` → `log()`.
- **Browser (AuditLogBrowser + view)**: actor names resolved per page (`User::whereIn`), diff column renders a readable summary (`message` → strip_tags; `from/to`; `old/new`; fallback truncated JSON), time column gains seconds, action filter lists all role/user/api-key/AI actions, entity filter adds user/portal-user/api-key/setting.
- **Repository**: `orderByDesc('id')` tiebreaker on all four queries (forEntity/search/recentForModule/recent); RolesManager Activity panel query too.
- Activity panel + PortalAccountService already tolerate the new shape (default dot color, sanitizer).

## 3. Verification

- [x] New `AuditRoleUserActionsTest` (5 tests): ip+correlation+entity on role/user changes and invite, plain diff, deterministic same-timestamp order, browser renders real names + no HTML leakage.
- [x] `php artisan test` — **1018 passed / 0 failed**; schema parity PASSED.
- [x] **Live smoke (as superadmin):** audit page renders real actor names (no `User #N`), clean diff column (no HTML/JSON leakage), IP column populated for story actions; role/user actions now share the exact same `log()` path as story rows (request-context capture proven in the HTTP tests).

## 4. Completion Notes

- Legacy rows keep their old HTML diffs; the browser strips tags on display so even
  legacy entries read cleanly.
- Note: `correlation_id` remains per-row UUID unless a client sends `X-Correlation-Id`
  (documented semantics in M10-HIST-005 — no middleware sets the header anywhere).
