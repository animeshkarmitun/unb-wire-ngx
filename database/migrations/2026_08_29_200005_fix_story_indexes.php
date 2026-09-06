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
            DB::statement('DROP INDEX IF EXISTS stories_status_published_at_index');
        } catch (Throwable $e) {
        }
        try {
            DB::statement('CREATE INDEX stories_status_published_at_index ON stories (status, published_at DESC)');
        } catch (Throwable $e) {
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        try {
            DB::statement('DROP INDEX IF EXISTS stories_status_published_at_index');
        } catch (Throwable $e) {
        }
        try {
            DB::statement('CREATE INDEX stories_status_published_at_index ON stories (status, published_at)');
        } catch (Throwable $e) {
        }
    }
};
