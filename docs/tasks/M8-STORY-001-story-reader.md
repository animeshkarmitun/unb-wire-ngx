# Task: M8-STORY-001 — Faithful Story Reader View (story.html + FR-NWS-019)

**Status:** ✅ Completed  
**Dependencies:** None  
**Parent Specification:** `app-data/story.html`, `app-data/v1-functional-requirements.md` (FR-NWS-019), DEC-007  

---

## 1. Contract (What)

- **Inputs / Validation:**
  - Route parameter publicId (string, ULID of the story).
  - Internal note submission: 
ewNoteBody (required, string, min: 2, max: 2000).
  - Status transition: 
ewStatus (in draft, in_review, changes_requested, pproved, published, killed, rchived).
- **Outputs / Response:**
  - Full-fidelity Blade/Livewire story reader page matching pp-data/story.html styled with admin chrome tokens.
  - Client-side wire export formats: Word (.doc), Text (.txt), XML (.xml), Image (.png canvas or asset), and Print.
  - Interactive social/clipboard share with visual feedback.
  - Real-time Asia/Dhaka clock with pulsing live indicator.
  - Immutable internal notes thread with instant appending.
- **Authorization:**
  - Viewing: bac:stories,view (enforced on route).
  - Editing: Link to dmin.add-news gated by bac:stories,update or ownership.
  - Status update / Unpublish: gated by RbacService::assertCan(auth()->user(), 'stories', 'edit') or eview/publish.

---

## 2. Logic (How)

1. **Component Initialization (App\Livewire\Admin\StoryView):**
   - Eager load story with category, subCategory, owner, 	ags, media, ersions.creator, 
otes.user, events.
   - Compute word count and estimated reading time (~200 wpm).
   - Fetch 3 related stories from same category or language.
   - Fetch 5 latest published stories from same wire language.
2. **Interactive Newsroom Actions:**
   - ddNote(): creates an immutable StoryNote (kind = 'editorial', is_internal = true, user_id = auth()->id()) and refreshes notes collection.
   - updateStatus(): validates transition, records StoryEvent, and updates story.
3. **Dispatch Exports & Client-side Features:**
   - Word export: formatted HTML Word document blob download (unb-story-{public_id}.doc).
   - Text export: clean wire formatted dispatch (unb-story-{public_id}.txt).
   - XML export: wire exchange XML format (unb-story-{public_id}.xml).
   - Image export: 1200x630 canvas banner generation or asset download (unb-story-{public_id}-featured.png).
   - Print trigger: native window.print().
   - Copy dispatch / Copy link with feedback toast/state.
4. **Layout & Visual Fidelity:**
   - Sub-masthead breadcrumb + back button (← English news / ← Bangla news).
   - Category badge, Fraunces serif headline (36px), serif subhead (19px).
   - Byline with brand, timestamp, word count, view count, and social share buttons.
   - Featured image / dynamic gradient (g1–g8) with camera SVG, caption, and photographer credit.
   - Story body with 54px dropcap on first paragraph, pull-quote with 3.5px crimson left border, and END/UNB/... signoff.
   - Attached media list & tags pills.
   - Related stories 3-card row (.card-row).
   - Sidebar (side):
     - Newsroom metadata & workflow card (status pill, public ID, priority, version history, internal notes thread).
     - Latest wire rail (.rail) with 5 stories and timestamps.

---

## 3. Context (Where)

- **Target Files:**
  - pp/Livewire/Admin/StoryView.php (Livewire controller)
  - esources/views/livewire/admin/story-view.blade.php (Blade template)
  - esources/views/admin/story.blade.php (Page wrapper)
  - 	ests/Feature/StoryViewTest.php (PHPUnit feature tests)
  - 	ests/e2e/story-reader-faithful.spec.ts (Playwright E2E tests)
- **Quality Gates:**
  - php artisan test --filter=StoryView
  - 
px playwright test tests/e2e/story-reader-faithful.spec.ts
  - php scripts/schema-parity-check.php
  - Zero PHP errors, zero N+1 queries.
