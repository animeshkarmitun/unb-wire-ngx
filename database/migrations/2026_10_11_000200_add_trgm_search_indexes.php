<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX stories_headline_trgm_index ON stories USING gin (headline gin_trgm_ops)');
            DB::statement('CREATE INDEX stories_brief_trgm_index ON stories USING gin (brief gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS stories_headline_trgm_index');
            DB::statement('DROP INDEX IF EXISTS stories_brief_trgm_index');
        }
    }
};
