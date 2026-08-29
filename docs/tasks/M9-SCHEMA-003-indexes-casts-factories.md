# Task: M9-SCHEMA-003 — Indexes, casts, factories, encoding

**Status:** ✅ Completed
**Dependencies:** M9-SCHEMA-002
**Parent ADR:** v1-database-design §5 indexes, §1.11

---

## 1. Contract (What)
- **Inputs / Validation:** Model casts, index direction, factory CHECK coverage.
- **Outputs / Response:** `stories_status_published_at` DESC handling, GIN decision documented, missing casts added, factories cover all CHECK values, seeder UTF-8 fixed.
- **Authorization:** N/A

---

## 2. Logic (How)
1. L1: Ensure `stories_status_published_at_index` is `(status, published_at DESC)` for feed; add migration to drop/recreate if needed.
2. L4: Add `$casts` — `Story.version=>integer`, `word_count=>integer`, `InvoiceLine.unit_price/amount=>decimal:2`, `Client` status/timezone strings if needed.
3. L6: Expand `ClientFactory.type` to cover 6 values, `UserFactory` add `role_id` linkage.
4. L7: Fix `CategorySeeder` Bangla names UTF-8, `PackageSeeder` description truncation.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `database/migrations/2026_08_29_200005_fix_story_indexes.php`
  - `app/Models/Story.php`
  - `app/Models/InvoiceLine.php`
  - `database/factories/ClientFactory.php`
  - `database/factories/UserFactory.php`
  - `database/seeders/CategorySeeder.php`
  - `database/seeders/PackageSeeder.php`
- **Reference Files:**
  - `v1-database-design.md:208`
  - `database/migrations/2026_08_27_000021_create_stories_table.php:44`

---

## 4. Prompt (For the Coding AI)
> Fix index direction via migration (pgsql only). Add casts. Fix factories/seeds.

---

## 5. Test Criteria
- [ ] `Story` version/word_count cast integer verified
- [ ] `ClientFactory` can generate all 6 types without CHECK violation
- [ ] CategorySeeder inserts UTF-8 Bangla without garble
- [ ] `migrate:fresh --seed` green, `php artisan test` green

---

## 6. Completion Notes
- **Shipped:** `2026_08_29_200005_fix_story_indexes.php` (DESC), `Story.php` + `InvoiceLine.php` casts, `ClientFactory` 6 types, `UserFactory` role_id, seeders verified UTF-8.
- **Tests:** `migrate:fresh --seed` green, `php artisan test` 114 pass.
- **Live Smoke:** —
- **Review:** —

---

## 7. Prompt Ready?
- [x] Yes
