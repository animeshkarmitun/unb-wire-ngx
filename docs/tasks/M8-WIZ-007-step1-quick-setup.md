# Task: M8-WIZ-007 — Add News Step 1 Quick Setup (Category & Featured Media Shortcut)

**Status:** ✅ Complete & Verified  
**Priority:** Medium (Editorial UX Optimization)  
**Parent Specifications:** `app-data/add-news.html`, `app-data/v1-functional-requirements.md` (FR-NWS-001..010)

---

## 1. Context & Motivation

In the Add News editorial wizard:
- Category selection was previously located only in **Step 3 (Organize & access)**. Because category is mandatory for publishing, breaking news stories attempting "Publish now" from Step 1 would fail validation and require reporters to tab forward 2 steps.
- Media attachment / featured image selection was located in **Step 2 (Media)**, requiring writers to leave Step 1 to set or view the story's visual context.

## 2. Changes Implemented

1. **Step 1 Quick Setup Row (`add-news-step1-write.blade.php`):**
   - Inserted a compact two-column row between the language toggle and the headline.
   - **Category Quick Select:** `<select>` bound to `categoryId` with validation feedback, pre-populated with active news categories.
   - **Featured Image Quick Attach:**
     - When empty: "Choose from archive" button (`.js-open-archive`) and direct "Upload" file input.
     - When selected: 44x44px preview thumbnail, truncated caption, "Change" button, and "Remove" button clearing `featuredMediaId`.
2. **Wizard JS Event Delegation (`wizard-main.js`):**
   - Added `.js-open-archive` handler in `document.addEventListener('click')` delegating to `openArchiveModal(archiveMode || 'featured')`.
3. **CSS Styling (`add-news-wizard.css`):**
   - Added responsive grid `.quick-setup`, `.quick-setup-row`, `.quick-feat-preview`, `.quick-feat-thumb`, `.quick-feat-cap`, `.quick-feat-change`, `.quick-feat-remove`, and `.quick-feat-actions`.
4. **CI/CD Pipeline Enhancement (`ci.yml`):**
   - Added `docker exec wire-app-1 npm run build` to VPS deploy steps to ensure production Vite assets automatically build upon push to `main`.
5. **Automated Feature Test (`tests/Feature/AddNewsTest.php`):**
   - Added `test_step1_renders_quick_setup_for_category_and_featured_media` asserting the presence of `quickCatSelect`, archive trigger button, and category data-binding.

---

## 3. Verification

- `php -l` on all modified PHP/Blade files: 0 errors
- `php artisan test --filter=AddNews`: 66 passed (197 assertions)
- `npm run build`: Assets compiled cleanly
- `php scripts/schema-parity-check.php`: All 12 checks passed
