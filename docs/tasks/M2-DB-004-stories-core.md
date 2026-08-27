# Task: M2-DB-004 — Stories core: stories, story_versions, story_notes, story_events, story_tag

**Status:** ✅ Completed
**Dependencies:** M2-DB-003
**Parent ADR:** v1-database-design §5 + §10.4

---

## 1. Contract (What)

- **Inputs / Validation:** No API inputs. Schema must match `v1-database-design.md §5` exactly.
- **Outputs / Response:** Migrations green on PostgreSQL 16, models cast correctly, `php artisan migrate:fresh --seed` passes.
- **Authorization:** Stories owned by `owner_id`/`created_by` (FK users); edit/publish gated by `role_permissions` via `RbacService`.

---

## 2. Logic (How)

1. Create `stories` — `id` identity PK, `public_id` ulid unique, `language` varchar(2) CHECK en/bn, `mirror_of_id` FK stories null, `status` varchar(20) default draft CHECK 7 values, `headline` varchar(300), `sub_head` varchar(300) null, `brief` varchar(280), `body_html` text, `body_text` text, `category_id` FK categories restrict, `sub_category_id` FK categories null, `dateline_city` varchar(80) null, `dateline_at` timestamptz null, `published_at`/`embargo_until` timestamptz null, `is_breaking` bool default false, `priority` varchar(12) CHECK routine/urgent/flash default routine, `source` varchar(12) CHECK desk/mojo/ai/wire default desk, `owner_id` FK users, `assigned_editor_id` FK users null, `locked_by` FK users null / `locked_at` timestamptz null, `version` bigint default 1, `ai_touched` jsonb null, `word_count` int null, `created_by` FK users, `created_at`/`updated_at` timestamptz default now(), `deleted_at` timestamptz null. Indexes: (status,published_at DESC), (language,status), (category_id,status), partial (owner_id) WHERE status NOT IN ('published','archived'), partial (published_at DESC) WHERE status='published'.
2. Create `story_versions` — `id` PK, `story_id` FK cascade, `version` bigint, `snapshot` jsonb, `created_by` FK users, `created_at` timestamptz. UNIQUE (story_id, version).
3. Create `story_notes` — `id` PK, `story_id` FK cascade, `user_id` FK users, `kind` varchar(8) CHECK note/system default note, `body` text, `created_at` timestamptz (no updated_at). Index (story_id, created_at).
4. Create `story_events` — `id` PK, `story_id` FK cascade, `actor_id` FK users null, `action` varchar(32), `from_status`/`to_status` varchar(20) null, `payload` jsonb null, `created_at` timestamptz (no updated_at).
5. Create `story_tag` pivot — `story_id` FK cascade, `tag_id` FK cascade, PK (story_id, tag_id).
6. Enums via varchar+CHECK pgsql-only, timestamptz everywhere, ULID, FKs as specced.

---

## 3. Context (Where)

- **Files to Create / Modify:**
  - `database/migrations/*_create_stories_table.php`
  - `database/migrations/*_create_story_versions_table.php`
  - `database/migrations/*_create_story_notes_table.php`
  - `database/migrations/*_create_story_events_table.php`
  - `database/migrations/*_create_story_tag_table.php`
  - `app/Models/Story.php`
  - `app/Models/StoryVersion.php`
  - `app/Models/StoryNote.php`
  - `app/Models/StoryEvent.php`
- **Reference Files:**
  - `app-data/v1-database-design.md §5, §10.4`
  - `app/Models/User.php` (ULID pattern)

---

## 4. Prompt (For the Coding AI)

> Implement stories core migrations per Contract. Use `timestamptz` everywhere, varchar+CHECK not pg enums (pgsql-only guards), ULID public_id, jsonb. Match column specs verbatim including defaults and FKs. Add indexes exactly as specced. Keep `migrate:fresh` green. Create models with fillable/casts/relations and ULID generation for Story.

---

## 5. Test Criteria

- [ ] `php artisan migrate:fresh` green on PostgreSQL 16
- [ ] `stories.language/status/priority/source` CHECKs reject invalid
- [ ] `stories.kind` etc CHECKs enforced
- [ ] `stories.public_id` unique, `story_versions` UNIQUE (story_id, version)
- [ ] `story_tag` PK (story_id, tag_id)
- [ ] FKs: stories.mirror_of_id self, category_id, owner_id, created_by, locked_by, etc.
- [ ] Indexes present (hot list, language/status, category/status, partial owner, partial published)
- [ ] All timestamp cols are timestamptz (bare timestamp = 0)
- [ ] `php -l` clean, `php artisan test` green

---

## 6. Completion Notes

- **Shipped:** 5 migrations (stories, story_versions, story_notes, story_events, story_tag) + 4 models (Story, StoryVersion, StoryNote, StoryEvent). ULID public_id, varchar+CHECK, timestamptz, jsonb, partial indexes as specced.
- **Tests:** `migrate:fresh` green (19 migrations). `php artisan test` 25/25 passed. `php -l` clean. Pg checks: language/status/priority/source/kind CHECKs OK, indexes (status+published, language+status, category+status, partial owner workload, partial published feed) verified, bare_timestamp=0.
- **Live Smoke:** N/A (DB-only task, no routes)
- **Review:** —

---

## 7. Prompt Ready?

- [x] Yes
