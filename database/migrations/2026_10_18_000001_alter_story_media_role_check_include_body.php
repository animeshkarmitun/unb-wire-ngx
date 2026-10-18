<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE story_media DROP CONSTRAINT IF EXISTS story_media_role_check');
            DB::statement("ALTER TABLE story_media ADD CONSTRAINT story_media_role_check CHECK (role IN ('featured','inline','body'))");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::table('story_media')->where('role', 'body')->delete();
            DB::statement('ALTER TABLE story_media DROP CONSTRAINT IF EXISTS story_media_role_check');
            DB::statement("ALTER TABLE story_media ADD CONSTRAINT story_media_role_check CHECK (role IN ('featured','inline'))");
        }
    }
};
