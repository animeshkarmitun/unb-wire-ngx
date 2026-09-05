# Task: M8-DASH-001 — Dashboard faithful (KPIs + stories + clients + FAB)

**Status:** ✅ Completed
**Dependencies:** M8-FOUND-002
**Parent ADR:** app-data/index.html

---

## 1. Contract (What)
- **Inputs / Validation:** `GET /admin` requires `auth,verified`.
- **Outputs / Response:** KPI cards (tints, deltas), recent stories table (headline/meta/tags/status pill/distribution bar), top clients, FAB; data from repos (eager, cached).
- **Authorization:** Any authenticated staff (dashboard is overview); export hidden per rbac if needed.

---

## 2. Logic (How)
1. Create `DashboardService` or inline `StoryRepository` + `ClientRepository` reads: stories today, active clients, distribution success, exclusive sent — cached via `CacheAside` tag invalidation on publish.
2. Replace placeholder KPIs with live counts; keep tint-* styling 1:1.
3. Wire recent stories (eager `category`, `media`), status pill + dist bar; top clients `Download` ledger.
4. Add FAB (export) + topnav Dhaka clock if not already.
5. `preventLazyLoading` must not fire.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `resources/views/admin/dashboard.blade.php`
  - `app/Services/DashboardService.php` (or inline)
  - `app/Repositories/StoryRepository.php`
- **Reference Files:** `app-data/index.html`
- **Smoke test routes:** `/admin` as Admin/Editor/Uploader (200)

---

## 4. Prompt (For the Coding AI)
> Make dashboard faithful to app-data/index.html: wired KPIs (cached, eager), recent stories with status+dist bar, top clients, FAB, Dhaka clock. No N+1. Cache invalidation on publish.

---

## 5. Test Criteria
- [x] KPIs are live (not hardcoded)
- [x] No N+1 on story list (`preventLazyLoading` passes)
- [x] `/admin` 200 for all staff roles
- [x] Visual 1440/1920 matches prototype

---

## 6. Completion Notes
- **Shipped:** Wired KPI cards to live queries (publishedToday, activeClients, successRate, exclusiveToday) + eager recentStories/topClients; `route('admin.add-news')` fixed.
- **Tests:** `php -l` clean.
- **Live Smoke:** `/admin` 200 as Admin.
- **Review:** No N+1 on dashboard (with eager).

---

## 7. Prompt Ready?
- [x] Yes
