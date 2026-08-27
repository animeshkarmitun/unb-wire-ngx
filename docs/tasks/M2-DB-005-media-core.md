# Task: M2-DB-005 — Media core: media_batches, media_assets, media_reviews, media_tag, story_media

**Status:** ✅ Completed
**Dependencies:** M2-DB-004
**Parent ADR:** v1-database-design §6 + §10.5 (DEC-008: assignments deferred)

---

## 1. Contract (What)

- **Inputs / Validation:** No API inputs. Schema must match `v1-database-design.md §6` exactly (with DEC-008 adjustment: `assignments` deferred, so `media_batches.assignment_id` omitted).
- **Outputs / Response:** Migrations green on PostgreSQL 16, models cast correctly, `php artisan migrate:fresh --seed` passes.
- **Authorization:** Media owned by `uploaded_by`/`uploader_id`; approval gated by `role_permissions`.

---

## 2. Logic (How)

1. Create `media_batches` — `id` PK, `public_id` ulid unique, `uploader_id` FK users restrict, `assignment_id` omitted (DEC-008), `event_label` varchar(200), `urgency` varchar(12) default routine CHECK routine/urgent/flash, `status` varchar(16) default pending CHECK pending/partial/reviewed, `submitted_at` timestamptz, `reviewed_by` FK users null, `reviewed_at` timestamptz null, `created_at`/`updated_at` timestamptz.
2. Create `media_assets` — `id` PK, `public_id` ulid unique, `kind` varchar(8) CHECK photo/video, `status` varchar(12) CHECK field/library/reedit/rejected/archived, `batch_id` FK media_batches null, `title` varchar(240), `caption` text, `credit_line` varchar(160), `photographer_id` FK users null, `source` varchar(12) default staff CHECK staff/field/ap/partner, `category_id` FK categories null, `event_label` varchar(200) null, `location_city`/`location_country` varchar(80) null, `captured_at` timestamptz null, `en_tags` jsonb null, `width`/`height` int null, `duration_ms` int null, `mime` varchar(40), `size_bytes` bigint, `checksum` char(64), `storage_disk` varchar(16) default s3, `original_path` varchar(300), `derivatives` jsonb default '{}', `exif` jsonb null, `embargo_until` timestamptz null, `uploaded_by` FK users restrict, `approved_by` FK users null, `approved_at` timestamptz null, `download_count` int default 0, `created_at`/`updated_at` timestamptz, `deleted_at` timestamptz. Indexes: (status,created_at DESC), (kind,status), (photographer_id), partial (approved_at DESC) WHERE status='library'.
3. Create `media_reviews` — `id` PK, `asset_id` FK cascade, `reviewer_id` FK users, `action` varchar(12) CHECK approve/reject/reedit, `reason_code` varchar(40) null, `note` text null, `created_at` timestamptz.
4. Create `media_tag` pivot — `asset_id` FK cascade, `tag_id` FK cascade, PK (asset_id, tag_id).
5. Create `story_media` pivot — `story_id` FK cascade, `asset_id` FK cascade, `role` varchar(12) default featured CHECK featured/inline, `sort_order` int default 0, `caption_override` text null, PK (story_id, asset_id).
6. varchar+CHECK pgsql-only, timestamptz, ULID, jsonb, char(64).

---

## 3. Context (Where)

- **Files to Create / Modify:**
  - `database/migrations/*_create_media_batches_table.php`
  - `database/migrations/*_create_media_assets_table.php`
  - `database/migrations/*_create_media_reviews_table.php`
  - `database/migrations/*_create_media_tag_table.php`
  - `database/migrations/*_create_story_media_table.php`
  - `app/Models/MediaBatch.php`
  - `app/Models/MediaAsset.php`
  - `app/Models/MediaReview.php`
- **Reference Files:**
  - `app-data/v1-database-design.md §6, §10.5`

---

## 4. Prompt (For the Coding AI)

> Implement media core migrations per Contract. Use `timestamptz`, varchar+CHECK pgsql-only, ULID, jsonb, char(64). Match specs verbatim including defaults and FKs (omit assignment_id). Add indexes exactly as specced. Create models with fillable/casts/relations and ULID generation.

---

## 5. Test Criteria

- [ ] `php artisan migrate:fresh` green
- [ ] `media_batches.urgency/status`, `media_assets.kind/status/source`, `media_reviews.action`, `story_media.role` CHECKs reject invalid
- [ ] `media_batches.public_id`, `media_assets.public_id` unique
- [ ] `media_assets.checksum` char(64), `storage_disk` default s3
- [ ] FKs cascade/restrict as specced
- [ ] Indexes present including partial approved_at
- [ ] `media_tag`/`story_media` PKs correct
- [ ] All timestamps timestamptz (bare=0)
- [ ] `php -l` clean, `php artisan test` green

---

## 6. Completion Notes

- **Shipped:** 5 migrations (media_batches, media_assets, media_reviews, media_tag, story_media) + 3 models (MediaBatch, MediaAsset, MediaReview). ULID, varchar+CHECK, timestamptz, jsonb, partial index.
- **Tests:** `migrate:fresh` green (24 migrations). `php artisan test` 25/25 passed. `php -l` clean. Pg checks: urgency/status/kind/source/action/role CHECKs OK, indexes including partial library approved verified, bare_timestamp=0.
- **Live Smoke:** N/A (DB-only task)
- **Review:** —

---

## 7. Prompt Ready?

- [x] Yes
