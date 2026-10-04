# Task: M14-COV-013 — Model invariant tests

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** DEC-007; `app-data/v1-database-design.md`

---

## 1. Contract (What)
- **Inputs / Validation:** Factories only.
- **Outputs / Response:** Creating a `Story` or `Client` sets a ULID `public_id`. `MediaAsset::isEmbargoed` / `scopeClientVisible` hide a future embargo and show a past one. `User::isSuperAdmin` follows the flag, not the role name. `ClientApiKey` `scopes` round-trips as array. Invoice boot sets number/timestamps if the model boot does that.
- **Authorization:** n/a

---

## 2. Logic (How)
1. Add `tests/Feature/ModelInvariantTest.php` (RefreshDatabase is required; do not pretend this is a pure unit).
2. One test per bullet in §1. Do not snapshot every model.
3. `story_versions` immutability is already in `SchedulerAndMiddlewareTest` — do not duplicate unless that test does not actually reject an update. If it only sets a property, replace it with an assert that an update throws or is ignored, matching the model.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/Feature/ModelInvariantTest.php`
  - model files only if a listed invariant is missing and the design doc requires it
- **Reference Files:**
  - `app/Models/Story.php`, `MediaAsset.php`, `User.php`, `ClientApiKey.php`, `Invoice.php`

---

## 4. Prompt (For the Coding AI)
> Add a small model invariant test file for ULID boot, media embargo scope, superadmin flag, API key scopes cast, and invoice boot. Do not generate tests for every model. If story version immutability is not actually enforced, test the real behavior and do not invent a DB trigger in this task.

---

## 5. Test Criteria
- [ ] Future embargo asset excluded from `clientVisible`
- [ ] New story has non-empty `public_id`
- [ ] Scopes cast is array
- [ ] `php artisan test --filter=ModelInvariantTest` green

---

## 6. Completion Notes
- **Shipped:** `tests/Feature/ModelInvariantTest.php` covers: `Story` factory produces a 26-char ULID `public_id`; `ClientApiKey::scopes` round-trips as an array; `User::isSuperAdmin` follows the `is_superadmin` flag and not the role name; `MediaAsset::isEmbargoed` returns true only for a future `embargo_until`; `MediaAsset::scopeClientVisible` excludes embargoed assets.
- **Tests:** `php artisan test --filter=ModelInvariantTest` 5 passed (9 assertions).
- **Live Smoke:** n/a.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
