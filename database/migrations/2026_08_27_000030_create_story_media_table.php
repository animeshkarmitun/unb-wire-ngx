<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_media', function (Blueprint $table) {
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->string('role', 12)->default('featured');
            $table->integer('sort_order')->default(0);
            $table->text('caption_override')->nullable();
            $table->primary(['story_id', 'asset_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE story_media ADD CONSTRAINT story_media_role_check CHECK (role IN ('featured','inline'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('story_media');
    }
};
