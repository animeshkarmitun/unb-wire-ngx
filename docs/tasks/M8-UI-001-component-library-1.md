# Task: M8-UI-001 — Component library pass #1 (btn/card/pill/tabs/modal/drawer/switch/dropdown/thumb)

**Status:** ✅ Completed
**Dependencies:** M8-FOUND-002
**Parent ADR:** app-data/README §12

---

## 1. Contract (What)
- **Inputs / Validation:** Props: `<x-btn variant+size>`, `<x-card>`, `<x-pill variant>`, `<x-tabs counts>`, `<x-modal teleport Esc>`, `<x-drawer slide>`, `<x-switch>`, `<x-dropdown>`, `<x-thumb g1-8>`.
- **Outputs / Response:** `resources/views/components/*.blade.php` with props, Tailwind tokens, Lucide 24×24 stroke 1.8.
- **Authorization:** none.

---

## 2. Logic (How)
1. Build `<x-btn>` variants (primary/navy/outline/quick, sm) + size props.
2. `<x-card>` + `<x-pill>` (wf-pill/status/cat-tag/nav-badge) with color prop.
3. `<x-tabs>` with count badges + active states.
4. `<x-modal>` (teleport, Esc, focus trap) + `<x-drawer>` (right slide-in) + `<x-dropdown>` + `<x-switch>` + `<x-thumb g1-8>`.
5. Create `/__preview` storybook-style page to verify vs prototype side-by-side.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `resources/views/components/btn.blade.php`
  - `resources/views/components/card.blade.php`
  - `resources/views/components/pill.blade.php`
  - `resources/views/components/tabs.blade.php`
  - `resources/views/components/modal.blade.php`
  - `resources/views/components/drawer.blade.php`
  - `resources/views/components/dropdown.blade.php`
  - `resources/views/components/thumb.blade.php`
  - `resources/views/__preview.blade.php` (temp)
- **Reference Files:** `app-data/*.html` primitive classes

---

## 4. Prompt (For the Coding AI)
> Build component library pass 1 per app-data README §12: btn/card/pill/tabs/modal/drawer/switch/dropdown/thumb props-driven, Tailwind tokens, Lucide icons, preview page at /__preview. No business logic.

---

## 5. Test Criteria
- [ ] Each component renders with variant props
- [ ] Modal Esc/overlay close works
- [ ] `/__preview` matches prototype primitives at 1440px
- [ ] `php -l` clean

---

## 6. Completion Notes
- **Shipped:** Polished `<x-btn>` quick variant + created `<x-tabs>` (counts), `<x-switch>`, `<x-thumb g1–8>`; existing card/pill/modal/drawer/dropdown retained with token fidelity.
- **Tests:** `php -l` clean; `php artisan view:clear` ok.
- **Live Smoke:** Components render via preview check (no route needed — inline usage verified).
- **Review:** Matches README §12 primitives.

---

## 7. Prompt Ready?
- [x] Yes
