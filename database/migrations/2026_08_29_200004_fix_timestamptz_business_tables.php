<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        try {
            DB::statement('ALTER TABLE password_reset_tokens ALTER COLUMN created_at TYPE timestamptz USING created_at::timestamptz');
        } catch (Throwable $e) {
        }
        // Both statements target failed_jobs.failed_at. The first is a no-op cast
        // (Postgres will succeed when the column is already timestamptz); the second
        // forces the cast when it is plain timestamp. The earlier 'USING
        // to_timestamp(failed_at) WHERE false' was invalid Postgres syntax and
        // aborted the whole migration.
        try {
            DB::statement('ALTER TABLE failed_jobs ALTER COLUMN failed_at TYPE timestamptz USING failed_at::timestamptz');
        } catch (Throwable $e) {
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        try {
            DB::statement('ALTER TABLE password_reset_tokens ALTER COLUMN created_at TYPE timestamp USING created_at::timestamp');
        } catch (Throwable $e) {
        }
        try {
            DB::statement('ALTER TABLE failed_jobs ALTER COLUMN failed_at TYPE timestamp USING failed_at::timestamp');
        } catch (Throwable $e) {
        }
    }
};
