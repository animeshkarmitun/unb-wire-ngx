# Task: COS-80 — Dashboard hides client business data from roles without clients:view

**Status:** 🔧 In Progress
**Priority:** P2 (RBAC data leak)
**Linear:** [COS-80](https://linear.app/cosmosgroupcom/issue/COS-80/role-uploaders-can-see-client-business-data-on-the-dashboard-clients-tiers)
**Affected surfaces:** `/admin` dashboard (DashboardService + `admin.dashboard` blade)
**Parent contracts:** FR-CLT / NFR §16.1 — RBAC enforced server-side, UI mirrors it

---

## 1. Issue Summary

Uploader roles (Uploader-English/Bangla — no `clients,view`) see business data on the
Dashboard: **Top clients today** (client names, tiers, download counts, package scope),
**Active clients (9)**, **Distribution success rate (98.4%)** and an **Export report**
button. The Clients page correctly 403s the same users.

## 2. Root Cause

`/admin` route is `auth+verified` only (intentional — it is every staff member's landing
page), and `DashboardService::getData()` computes **all** sections unconditionally,
including a shared 60s `dashboard:kpis` cache that mixes newsroom KPIs with client
business KPIs.

## 3. Fix Design

- `DashboardService::getData(?User $user = null)` — client-business data (active clients
  delta, distribution success rate, top clients) is computed/returned **only** when the
  user passes `RbacService::can($user, 'clients', 'view')`. Shared cache split in two:
  `dashboard:kpis` (newsroom) + `dashboard:clients` (business) so an uploader can never
  receive a cached blob containing business KPIs.
- Route passes `auth()->user()`.
- Blade: Active clients card, Distribution success rate card, Top clients panel and the
  Export report button gated with the established idiom
  (`app(RbacService::class)->can(auth()->user(), 'clients', 'view')`).
- "Export report" is currently a toast-only stub — hidden for unauthorized roles.

## 4. Verification Plan

- [x] `DashboardTest` additions: uploader dashboard 200 **without** business sections;
      admin dashboard still contains them; service-level `getData($uploader)` has no
      `topClients`/`activeClients` keys.
- [x] `RbacEndpointTest::test_dashboard_accessible_to_all_authenticated` updated to
      assert the data-level difference (still 200 for all staff).
- [x] Gates: `php -l`, `php artisan test` — **996 passed / 0 failed**, schema parity PASSED
      (no schema change).
- [ ] Live smoke on test server as `tanvir@unbnews.org` (Uploader-Bangla) + admin.

## 5. Completion Notes

- Business KPI cache split: `dashboard:kpis` (newsroom) + `dashboard:clients` (business) —
  an uploader can never receive a cached business blob (previous cache mixed both).
- Sidebar raw Clients link for unauthorized roles is a separate known issue (COS-65).
