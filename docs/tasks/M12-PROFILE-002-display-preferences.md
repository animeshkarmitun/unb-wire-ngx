# Task: M12-PROFILE-002 — FR-ACC-005 display preferences (date format + density, consume prefs)

**Status:** ⏳ Pending
**Dependencies:** None (parallelizable with M12-PROFILE-001)
**Parent ADR:** FR-ACC-005 (per-user display prefs — presentation only, never overrides server truth), DEC-011 (varchar + CHECK enums, schema parity gate)

---

## 1. Contract (What)
- **Inputs (Preferences form, Livewire validation):**
  - `desk`: existing options (English desk, Bangla desk, Business, Photo desk) — unchanged
  - `timezone`: existing options (Asia/Dhaka, UTC) — unchanged
  - `date_format` (new): `dmy` (`j M, h:i A`, default) | `mdy` (`M j, h:i A`) | `iso` (`Y-m-d H:i`)
  - `density` (new): `comfortable` (default) | `compact`
- **Outputs:** Prefs persist on `users`; story/audit/note timestamps render in the user's timezone + date format; `density` toggles a compact layout hook. Server truth untouched (storage stays UTC).
- **Out of scope:** Masthead/topnav live Dhaka clock stays hardcoded business time (brand element, not user content); portal (Next.js) prefs are a separate future task.

---

## 2. Logic (How)
1. Migration `alter_users_table_add_display_prefs`: `date_format varchar(16) DEFAULT 'dmy'` + CHECK in (`dmy`,`mdy`,`iso`); `density varchar(16) DEFAULT 'comfortable'` + CHECK in (`comfortable`,`compact`) — DEC-011 conventions; must pass `php scripts/schema-parity-check.php`.
2. Add both to `User::$fillable`.
3. Central helper (single consumption point, e.g. `App\Support\DisplayPrefs::format(?Carbon $dt)` resolving `auth()->user()` timezone + date_format with safe defaults for guests/console) — no new per-view timezone logic.
4. Replace hardcoded `->timezone('Asia/Dhaka')->format(...)` with the helper in: `livewire/admin/story-view.blade.php` (timeline line ~616, versions line ~657), `livewire/admin/news-list.blade.php` (notes lines ~322/328), `livewire/admin/audit-log-browser.blade.php` (line ~65), `livewire/admin/wire-service-view.blade.php` (lines ~163/220/277), `Livewire/Admin/AddNews.php` (line ~604).
5. Density: `x-admin-layout` emits `data-density="{{ auth()->user()->density ?? 'comfortable' }}"`; add compact overrides for the data-table component (reduced row padding/font) scoped under `[data-density="compact"]`.
6. Extend `App\Livewire\Admin\Preferences` + its Blade view with the two new selects + Livewire `#[Validate]` rules; keep `toast` success pattern.

---

## 3. Context (Where)
- **Files to Create:**
  - `database/migrations/<ts>_alter_users_table_add_display_prefs.php`
  - `app/Support/DisplayPrefs.php` (or equivalent single helper)
  - `tests/Feature/PreferencesTest.php`
- **Files to Modify:**
  - `app/Models/User.php` (fillable)
  - `app/Livewire/Admin/Preferences.php` + `resources/views/livewire/admin/preferences.blade.php`
  - Blade files listed in Logic step 4 + admin layout (density hook) + data-table CSS
  - `tests/e2e/ui-admin-auth.spec.ts` visits `/admin/preferences` — extend with prefs-save assertion if cheap
- **Reference:**
  - `database/migrations/2026_08_27_000013_alter_users_table_add_auth_fields.php` (timezone/desk precedent)
  - `docs/knowledge-inventory/data-model.md` (update users row in same commit per doc-sync gate)
  - `app-data/v1-functional-requirements.md` FR-ACC-005 (lines 497–499)

---

## 4. Prompt (For the Coding AI)
> Implement M12-PROFILE-002. Add date_format/density columns to users with CHECK constraints (DEC-011) and fillable entries. Create a single App\Support\DisplayPrefs::format() helper honoring the authed user's timezone+date_format, and use it to replace hardcoded Asia/Dhaka timestamp formatting in story-view, news-list notes, audit-log browser, wire-service-view, and AddNews. Add data-density hook on x-admin-layout with compact data-table CSS. Extend the Preferences Livewire component with validated date_format/density selects. Add tests/Feature/PreferencesTest.php. Update data-model.md in the same commit.

---

## 5. Test Criteria
- [ ] Migration fresh-runs; `php scripts/schema-parity-check.php` passes
- [ ] Invalid `date_format`/`density` rejected by validation; valid values persist
- [ ] Helper renders a fixed UTC timestamp correctly for Asia/Dhaka+dmy, UTC+iso, and guest default
- [ ] Story timeline, versions, news notes, audit browser show user-timezone timestamps (feature test asserting rendered output)
- [ ] `density=compact` sets `data-density="compact"` on admin layout
- [ ] `php artisan test` green; `php -l` clean; live smoke `/admin/preferences` save + `/admin/news/en` timestamp render

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
