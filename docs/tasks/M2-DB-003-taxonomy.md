# Task: M2-DB-003 — Taxonomy: categories, tags

**Status:** ✅ Completed
**Dependencies:** M2-DB-002
**Parent ADR:** v1-database-design §4 + §10.3

---

## 1. Contract (What)

- **Inputs / Validation:** No API inputs. Schema must match `v1-database-design.md §4` exactly.
- **Outputs / Response:** Migrations green on PostgreSQL 16, models cast correctly, `php artisan migrate:fresh --seed` passes.
- **Authorization:** N/A (taxonomy is public lookup; write gated by `RbacService` elsewhere).

---

## 2. Logic (How)

1. Create `categories` — `id` bigint identity PK, `slug` varchar(60) unique, `name_en` varchar(80), `name_bn` varchar(80), `parent_id` FK categories null (self-FK, nullOnDelete cascade? spec says null=top level), `sort_order` smallint default 0. No timestamps per spec (only these columns).
2. Create `tags` — `id` identity PK, `name` varchar(80) unique, `slug` varchar(90) unique. No timestamps per spec.
3. Pivots `story_tag` and `media_tag` are deferred — they require `stories`/`media_assets` tables (M2-DB-004/005) so not created here.
4. Use `timestamptz` if timestamps added; otherwise no timestamp columns. Varchar lengths exactly as specced. Self-FK on categories with nullOnDelete/set null.

---

## 3. Context (Where)

- **Files to Create / Modify:**
  - `database/migrations/*_create_categories_table.php`
  - `database/migrations/*_create_tags_table.php`
  - `app/Models/Category.php`
  - `app/Models/Tag.php`
- **Reference Files:**
  - `app-data/v1-database-design.md §4, §10.3`
  - `database/migrations/2026_08_27_000011_create_roles_table.php` (migration pattern)

---

## 4. Prompt (For the Coding AI)

> Implement taxonomy migrations per Contract. Match column specs verbatim. Self-FK parent_id on categories (cascadeOnDelete or nullOnDelete). Keep `migrate:fresh` green. Create models with fillable/relations.

---

## 5. Test Criteria

- [ ] `php artisan migrate:fresh` green on PostgreSQL 16
- [ ] `categories.slug` unique, `tags.name` unique, `tags.slug` unique
- [ ] `categories.parent_id` FK self-references categories, nullable
- [ ] `categories.sort_order` default 0
- [ ] Models Category/Tag have correct fillable and relations (parent/children)
- [ ] `php -l` clean, `php artisan test` green

---

## 6. Completion Notes

- **Shipped:** 2 migrations (categories, tags) + 2 models (Category, Tag). Self-FK parent_id nullOnDelete, unique slugs/names, sort_order default 0.
- **Tests:** `migrate:fresh` green (14 migrations). `php artisan test` 25/25 passed. `php -l` clean. Pg checks: categories_slug/tag_name/tag_slug unique OK, self-FK SET NULL verified.
- **Live Smoke:** N/A (DB-only task, no routes)
- **Review:** —

---

## 7. Prompt Ready?

- [x] Yes
