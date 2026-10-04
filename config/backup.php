<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backups Enabled
    |--------------------------------------------------------------------------
    |
    | Kill switch for the automated database backup. When false, backup:run
    | is a no-op (unless --force) and the scheduler entry stays idle.
    |
    */
    'enabled' => env('BACKUP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Schedule Time
    |--------------------------------------------------------------------------
    |
    | Time of day (HH:MM, app timezone) for the daily backup:run schedule.
    |
    */
    'schedule' => env('BACKUP_SCHEDULE', '02:00'),

    /*
    |--------------------------------------------------------------------------
    | Offsite Disk
    |--------------------------------------------------------------------------
    |
    | Backups are always stored on the local disk first ("store locally").
    | When set to another disk name (e.g. "s3"), each backup is also copied
    | there afterwards ("then S3 for offsite"). "local" keeps local only.
    |
    */
    'disk' => env('BACKUP_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Daily Retention Window
    |--------------------------------------------------------------------------
    |
    | Number of days of backups kept as the daily set. Rotation additionally
    | keeps 4 weekly (newest per ISO week) and 3 monthly (newest per month)
    | copies; everything older is pruned.
    |
    */
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Failure Alert Recipient
    |--------------------------------------------------------------------------
    |
    | Email address that receives the BackupFailed mailable when a run fails.
    | When empty, failures are only logged.
    |
    */
    'alert_email' => env('BACKUP_ALERT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | pg_dump Binary
    |--------------------------------------------------------------------------
    |
    | Path to the pg_dump executable. Overridable for custom installs and
    | for tests that exercise the failure path.
    |
    */
    'pg_dump_bin' => env('BACKUP_PG_DUMP_BIN', 'pg_dump'),

];
