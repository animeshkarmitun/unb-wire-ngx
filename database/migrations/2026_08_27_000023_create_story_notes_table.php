<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 8)->default('note');
            $table->text('body');
            $table->timestamptz('created_at')->useCurrent();

            $table->index(['story_id', 'created_at'], 'story_notes_story_created_index');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE story_notes ADD CONSTRAINT story_notes_kind_check CHECK (kind IN ('note','system'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('story_notes');
    }
};
