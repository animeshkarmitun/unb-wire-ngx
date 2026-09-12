# Task: M11-HOOK-003 — Respect webhook trigger filters

**Status:** ⏳ Pending
**Dependencies:** M11-HOOK-001
**Parent ADR:** FR-DST-002, `app-data/delivery-settings.html` Card 2 triggers

---

## 1. Contract (What)
- **Inputs:** Story attributes (`is_breaking`, category, media kinds, embargo status) + channel `config.triggers` object
- **Outputs:** Boolean decision: should this webhook fire for this story?
- **Trigger filters** (from `DeliverySettings` Card 2):
  - `breaking` — only fire if `story.is_breaking === true`
  - `media_pack` — only fire if story has ≥1 media attachment
  - `exclusive` — only fire if story has `is_exclusive` flag (or tag)
  - `embargoed` — only fire if story had `embargo_until` set
- **Default behavior:** If no triggers configured, fire on all published stories (backward compat)

---

## 2. Logic (How)
1. Create `App\Services\Delivery\TriggerMatcher` with `shouldFire(Story $story, array $triggers): bool`.
2. If `$triggers` is empty or all false → return `true` (fire on everything — backward compat).
3. If any trigger is `true`, story must match at least ONE active trigger.
4. In `FanoutStory.php`, after entitlement check and before webhook dispatch, call `TriggerMatcher::shouldFire()`. If false, mark delivery as `skipped_entitlement` and skip.

---

## 3. Context (Where)
- **Files to Create:**
  - `app/Services/Delivery/TriggerMatcher.php`
- **Files to Modify:**
  - `app/Jobs/FanoutStory.php` (add trigger check before webhook dispatch)
- **Reference:**
  - `app/Livewire/Admin/DeliverySettings.php` lines 70–79 (trigger properties)
  - `deliveries` status CHECK includes `'skipped_entitlement'`
- **Tests to Create:**
  - `tests/Feature/TriggerMatcherTest.php` — unit tests for each trigger combination

---

## 4. Prompt (For the Coding AI)
> Implement M11-HOOK-003. Create `TriggerMatcher` service. Integrate into `FanoutStory` before webhook dispatch. When triggers are configured but story doesn't match, set delivery status to `skipped_entitlement`. Write tests for: all triggers false (fire all), breaking-only trigger, media_pack trigger, multiple triggers (OR logic), empty triggers (backward compat).

---

## 5. Test Criteria
- [ ] No triggers configured → webhook fires for all stories
- [ ] `breaking: true` trigger → fires only for `is_breaking` stories
- [ ] `media_pack: true` → fires only for stories with media
- [ ] Non-matching stories get `status = 'skipped_entitlement'` in deliveries
- [ ] Existing `FanoutAdvancedTest` passes
- [ ] `php -l` clean

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
