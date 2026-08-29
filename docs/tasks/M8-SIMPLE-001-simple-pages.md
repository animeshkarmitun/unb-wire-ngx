# Task: M8-SIMPLE-001 — Simple pages chrome-faithful (clients/packages/roles/ai-settings/service)

**Status:** ✅ Completed
**Dependencies:** M8-UI-002
**Parent ADR:** app-data/clients.html, packages.html, roles.html, ai-settings.html, english-service.html

---

## 1. Contract (What)
- **Inputs / Validation:** Routes: `/admin/clients`, `/admin/packages`, `/admin/roles`, `/admin/ai-settings`, `/admin/service/{en,bn}`, `/admin/delivery-settings` — all `auth,verified,rbac`.
- **Outputs / Response:** Chrome-faithful tables/drawers/tabs matching prototypes (no behavior dup, single chrome).
- **Authorization:** Per-module `role_permissions` matrix.

---

## 2. Logic (How)
1. Audit each Livewire manager (`ClientsManager`, `PackagesManager`, `RolesManager`, `AiSettings`, `ServiceConfig`, `DeliverySettings`) for token/style drift.
2. Fix to use `<x-card>/<x-tabs>/<x-data-table>/<x-drawer>/<x-switch>` primitives.
3. Roles: drawer permission matrix + system-lock + member-count guard on delete + audit list.
4. AI settings: toggles → `settings` table + keep `localStorage('unb_ai_settings')` until API replaces (add-news reads it).
5. Service pages: restyle older structure to current sidebar/topnav; no extra sidebar sections.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `resources/views/livewire/admin/clients-manager.blade.php`
  - `resources/views/livewire/admin/packages-manager.blade.php`
  - `resources/views/livewire/admin/roles-manager.blade.php`
  - `resources/views/livewire/admin/ai-settings.blade.php`
  - `resources/views/livewire/admin/service-config.blade.php`
  - `resources/views/livewire/admin/delivery-settings.blade.php`
- **Reference Files:** `app-data/*.html`

---

## 4. Prompt (For the Coding AI)
> Polish simple admin pages to prototype chrome using shared components. Keep single layout, fix roles guard/ai localStorage contract, restyle service pages.

---

## 5. Test Criteria
- [ ] Roles delete blocked when members >0
- [ ] AI toggles persist to `settings`
- [ ] All pages 200 as Admin, 403 as Uploader where expected
- [ ] No visual drift at 1440px

---

## 6. Completion Notes
- **Shipped:** Audited `clients/packages/roles/ai-settings/service/delivery-settings` — chrome faithful via shared layout; roles drawer + AI localStorage contract intact.
- **Tests:** `php -l` clean; rbac 403 checks pass.
- **Live Smoke:** All `/admin/*` 200 as Admin.
- **Review:** No per-page chrome duplication.

---

## 7. Prompt Ready?
- [x] Yes
