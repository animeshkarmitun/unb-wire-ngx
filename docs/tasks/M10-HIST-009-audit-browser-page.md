# Task: M10-HIST-009 — Global audit log browser (`/admin/audit`)

**Status:** ⏳ Pending
**Dependencies:** M10-HIST-005
**Parent ADR:** FR-NTF-003 ("per-entity audit history is viewable by admins"), NFR §15 (`AuditQueryService`), `docs/plans/history-audit-design.md` §4

---

## 1. Contract (What)
- **Inputs / Validation:** Route `GET /admin/audit` + lazy Livewire `AuditLogBrowser`. Filters: actor (user search), `action`, `entity_type`, `entity_id`, date range (`from`/`to`). Deep-link support: `?entity_type=Story&entity_id=X` (linked from story view audit chip).
- **Outputs / Response:** Paginated table (25/page): timestamp (Dhaka tz), actor (name + type badge user/system/worker), action, entity (type + id, story rows link to `/admin/news/{id}`), diff summary (expandable), ip, correlation id. Empty-state + pagination chrome per design tokens.
- **Authorization:** `RbacService.assertCan(user, 'audit', 'view')` on route + component mount — Editor/Strategist/Business/Client/Uploaders → 403. Sidebar "Audit Log" entry renders only for permitted roles.

---

## 2. Logic (How)
1. Route in admin group (session auth middleware) + `assertCan` gate.
2. `AuditLogBrowser` lazy component: filter form state → `AuditQueryService::search(...)` — no queries in Blade, no N+1 (actor eager).
3. RolesManager "Activity" tab keeps its summary (last 15) — unchanged; this page is the full browser.
4. Sidebar entry: conditional render on `can('audit','view')`.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `routes/web.php` (admin group)
  - `app/Livewire/Admin/AuditLogBrowser.php` (new, lazy)
  - `resources/views/livewire/admin/audit-log-browser.blade.php` (new)
  - admin layout sidebar (conditional entry)
  - `tests/Feature/AuditBrowserTest.php` (new), `tests/e2e/audit-browser.spec.ts` (new)
- **Reference Files:** `app/Services/AuditQueryService.php`, M8-UI-002 `data-table`/`filter-bar` components, `app-data/roles.html` Activity styling

---

## 4. Prompt (For the Coding AI)
> Build /admin/audit: lazy AuditLogBrowser with filter-bar (actor search, action, entity_type, entity_id, date range) + paginated data-table (25) from AuditQueryService::search. Row: Dhaka-tz time, actor + type badge, action, entity link (Story → /admin/news/{id}), expandable diff summary, ip, correlation id. assertCan audit.view on route + mount (403 for non-audit roles). Sidebar entry conditional on permission. Deep-link ?entity_type&entity_id pre-applies filter. Tests: 403 Editor, 200 Admin; filters + pagination; no N+1.

---

## 5. Test Criteria
- [ ] Feature: Editor (audit=0) → 403; Admin + Admin Report → 200
- [ ] Feature: filters (actor/action/entity/date) narrow results; pagination works
- [ ] Feature: deep-link `?entity_type=Story&entity_id=X` pre-filters
- [ ] Sidebar: entry hidden for Editor, visible for Admin
- [ ] No N+1 on 25-row page (query-count assertion)
- [ ] `php artisan test` + E2E + `npm run build` green

---

## 6. Completion Notes
- **Shipped:** —
- **Tests:** —
- **Live Smoke:** —
- **Review:** —

---

## 7. Prompt Ready?
- [x] Yes
