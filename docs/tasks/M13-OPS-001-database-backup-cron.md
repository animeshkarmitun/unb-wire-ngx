# Task: M13-OPS-001 — Automated database backup cron job

**Status:** ✅ Completed
**Dependencies:** M1-BOOT-001
**Parent ADR:** NFR §15 ops (Horizon/scheduler), `app-data/v1-functional-requirements.md` FR-OPS (backup continuity)

---

## 1. Contract (What)
- **Inputs / Validation:** Env config `BACKUP_ENABLED`, `BACKUP_SCHEDULE` (HH:MM), `BACKUP_DISK` (offsite disk name or `local`), `BACKUP_RETENTION_DAYS` (daily window, default 7), `BACKUP_ALERT_EMAIL`, `BACKUP_PG_DUMP_BIN` (binary path override). DB connection params read from `config('database.connections.pgsql')`.
- **Outputs / Response:**
  - `backup:run {--prune-only}` — pg_dump → temp SQL → gzip → `backups/db-YYYYMMDD-HHMMSS.sql.gz` on the local disk; optional copy to `BACKUP_DISK` ("store locally then S3 for offsite"); then retention prune. Exit 0 on success / disabled no-op, exit 1 + failure alert on error.
  - `backup:list` — table: name, size, created (newest first).
  - Retention policy (issue-fixed): **7 daily** (all backups within `BACKUP_RETENTION_DAYS`), **4 weekly** (newest per ISO week, last 4 weeks), **3 monthly** (newest per month, last 3 months); everything else deleted.
- **Authorization:** Console-only (scheduler/ops), no HTTP surface.
- **Alerts:** On failure → `Log::error` + `App\Mail\BackupFailed` to `BACKUP_ALERT_EMAIL` when set (Slack deferred — no Slack infra in stack).

---

## 2. Logic (How)
1. `backup:run`: `enabled` gate (no-op when `BACKUP_ENABLED=false`, unless `--force`) → run `pg_dump -f <tmp>` via Symfony Process (PGPASSWORD env) → chunked `gzopen/gzwrite` to `backups/db-<timestamp>.sql.gz` on `Storage::disk('local')` → copy to offsite disk when `BACKUP_DISK` differs → `prune($disk)` on each active disk → info line with size.
2. `prune($disk)`: list `backups/db-*.sql.gz`, parse timestamp from filename, bucket into daily/weekly/monthly keep-sets (newest per week/month wins), delete non-kept.
3. Failure (Process error, missing binary, storage error): catch → `Log::error` → `Mail::to(alert_email)->send(new BackupFailed($error))` when configured → return 1.
4. Scheduler (`routes/console.php`): `Schedule::command('backup:run')->dailyAt(config('backup.schedule'))->withoutOverlapping()->name('db-backup')`.
5. Transaction boundaries: none needed (filesystem + process only).

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `config/backup.php` (new)
  - `app/Console/Commands/BackupDatabase.php` (new)
  - `app/Console/Commands/BackupList.php` (new)
  - `app/Mail/BackupFailed.php` (new) + `resources/views/mail/backup-failed.blade.php` (new)
  - `routes/console.php` (schedule entry)
  - `.env.example` (BACKUP_* vars)
  - `tests/Feature/BackupCommandsTest.php` (new)
  - `docs/knowledge-inventory/architecture.md` (ops note)
- **Reference Files:**
  - `app/Console/Commands/CheckOutboxLag.php` (command style)
  - `app/Mail/StoryAlert.php` + `resources/views/mail/story-alert.blade.php` (mailable style)
  - `config/filesystems.php` (disks)

---

## 4. Prompt (For the Coding AI)
> Create `backup:run {--prune-only} {--force}` + `backup:list` commands per §2, config per §1, `BackupFailed` mailable (plain blade, `[UNB Wire] Database backup failed` subject), schedule entry in `routes/console.php` at `dailyAt(config('backup.schedule','02:00'))`. `dumpToFile(string $sqlPath): protected` is the only seam: real impl runs pg_dump; tests override via registered subclass. Retention: 7 daily window / 4 weekly (newest per ISO week) / 3 monthly (newest per month), filename `db-YYYYMMDD-HHMMSS.sql.gz` under `backups/`. Tests (Storage::fake + Mail::fake): disabled no-op, prune buckets, list output, failure alert (bogus `BACKUP_PG_DUMP_BIN` → exit 1 + BackupFailed sent), success path via overridden `dumpToFile` (gzip exists on disk, offsite copy, prune runs).

---

## 5. Test Criteria
- [x] `backup:run` disabled → no files, exit 0
- [x] `--prune-only` keeps 7 daily / 4 weekly / 3 monthly, deletes redundant (Storage::fake)
- [x] `backup:list` renders names + sizes newest first
- [x] Failure path: bad binary → exit 1 + `BackupFailed` mail + error log (also: no mail when alert_email empty)
- [x] Success path (dumpToFile stub): `.sql.gz` written, gzip valid, offsite copy when `BACKUP_DISK` set
- [x] `php artisan schedule:list` shows `db-backup`
- [x] `php -l` clean; full `php artisan test` green

---

## 6. Completion Notes
- **Shipped:** `config/backup.php` (BACKUP_ENABLED/SCHEDULE/DISK/RETENTION_DAYS/ALERT_EMAIL/PG_DUMP_BIN); `backup:run {--prune-only} {--force}` (pg_dump via Symfony Process with PGPASSWORD → chunked PHP gzip → local disk → optional offsite copy → GFS prune); `backup:list` (table, newest first); `BackupFailed` mailable + blade (styled on story-alert); scheduler entry `db-backup` daily at `BACKUP_SCHEDULE` in `routes/console.php`; `.env.example` vars; architecture.md §3 sync. Retention: daily window + newest-per-ISO-week × 4 + newest-per-month × 3, union-kept. Alert email sent only when `BACKUP_ALERT_EMAIL` set (Slack deferred — no Slack infra). `dumpToFile` protected seam is the single test/ops override point.
- **Tests:** `tests/Feature/BackupCommandsTest.php` 6 passed (35 assertions) — disabled no-op, prune buckets (17 fixture files over fixed `Carbon::setTestNow(2026-09-22 12:00)` calendar → 11 kept / 6 pruned), list output, failure alert (+ no-mail variant), success gzip+offsite via stub subclass. Full suite: **725 passed, 1 skipped, 0 failures**; `php -l` clean on all 6 files; schema parity PASSED (no schema change).
- **Live Smoke:** `optimize:clear`; `schedule:list` shows `0 2 * * * php artisan backup:run` next due; `backup:list` → "No backups on [local]"; `backup:run --prune-only` → "Pruned 0 old backup(s)", exit 0. (Real pg_dump left for staging with pg credentials — CI pgsql exercises the failure path deterministically.)
- **Review:** (PR review — pending CI)

---

## 7. Prompt Ready?
- [x] Yes
