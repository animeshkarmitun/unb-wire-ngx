# Task: M8-SERV-001 — Faithful Wire Service Frontpage (english-service.html + bn parity)

**Status:** ✅ Completed  
**Dependencies:** None  
**Parent Specification:** `app-data/english-service.html`, `app-data/README.md` (§1, §2, §4, §11), `DEC-007`  

---

## 1. Contract (What)

- **Inputs / Validation:**
  - Route parameter `service` (`en` or `bn`).
  - Search query `search` (optional string, reactive debounce).
  - Selected category filter `activeCategory` (optional string, slug or ID).
  - Rail tab `activeRailTab` (`latest` or `popular`).
- **Outputs / Response:**
  - Full-fidelity Blade/Livewire wire service frontpage matching `app-data/english-service.html` styled with admin chrome tokens.
  - Brand strip with crimson/amber/navy gradient.
  - Sub-masthead with "U" monogram mark, "UNB — United News of Bangladesh", wire subtitle ("English Service" / "Bangla Service"), live Asia/Dhaka clock with pulsing green dot, search input, and "+ Add News" action.
  - Sticky category navigation bar with dynamic active state and underline indicator.
  - News Updates / Breaking Wire ticker with pulsing dot and headline rotation.
  - 2-column wire home grid:
    - **Hero Story**: 16:9 aspect ratio, image or `g1`–`g8` placeholder, crimson category tag, Fraunces serif headline (28px), and dateline/byline metadata.
    - **Category Sections**: Dynamic section rows (e.g. Bangladesh, World, Business, Sports) each with crimson-underlined header, "View all →" link to `/admin/news/{service}?category=...`, and 4-card story row (`.card-row`, `.story-card`) with hover lift, gradient/photo thumbnail, category badge, serif title, and timestamp.
    - **Right Rail (`aside .rail`)**: Latest vs Popular/Most Read tabbed list (5 items each) with thumbnail icons, headlines, timestamps, and view counts.
  - Wire Service settings modal/drawer to configure wire name, description, and status (`Setting service.{lang}`).
  - Footer with UNB branding, wire service tag, and continuous update notice.
- **Authorization:**
  - Gated by `rbac:stories,view`.
  - Wire configuration editing gated by `rbac:settings,edit` or `rbac:distribution,edit`.

---

## 2. Logic (How)

1. **Component Initialization (`App\Livewire\Admin\WireServiceView`):**
   - Eager load categories with active status and stories count.
   - Fetch Hero story: top breaking story or latest published story in selected language (`with(['category', 'media'])`).
   - Fetch section stories grouped by top categories (4 stories per category section).
   - Fetch Latest 5 stories and Top 5 stories (by views or publication order) for the rail tabs.
   - Fetch latest breaking news updates for the ticker.
2. **Wire Service Settings Drawer:**
   - Admin can click "Configure Service" to open a slide-over/modal with `wireName`, `description`, `enabled`, persisting to `Setting service.{service}`.
3. **Bangla (`bn`) Parity:**
   - When `service === 'bn'`, renders Bengali typography (`Noto Sans Bengali`), Bengali category names (`name_bn`), Bengali wire dispatches, and Bengali date formatting.
4. **Links & Navigation:**
   - Hero, section cards, and rail items link directly to `/admin/story/{public_id}` (Story Reader).
   - Category "View all →" links to `/admin/news/{service}?category={cat_id}`.
   - Search input reactively filters stories or highlights matching dispatches.

---

## 3. Context (Where)

- **Target Files:**
  - `app/Livewire/Admin/WireServiceView.php` (New Livewire component replacing basic `ServiceConfig.php` as primary page component)
  - `resources/views/livewire/admin/wire-service-view.blade.php` (Blade template for wire frontpage)
  - `resources/views/admin/service.blade.php` (Page view wrapper)
  - `resources/css/wire-service.css` (Dedicated styling matching `english-service.html`)
  - `resources/css/app.css` (Import `wire-service.css`)
  - `tests/Feature/WireServiceViewTest.php` (Feature test suite)
  - `tests/e2e/wire-service-faithful.spec.ts` (Playwright E2E test suite)
- **Quality Gates:**
  - `php artisan test --filter=WireServiceViewTest`
  - `npx playwright test tests/e2e/wire-service-faithful.spec.ts`
  - `php scripts/schema-parity-check.php`
  - `npm run build`
  - Zero PHP errors, zero N+1 queries.
