<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->nullable()->constrained('stories')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 12);
            $table->string('prompt_version', 20);
            $table->string('model', 60);
            $table->char('input_hash', 64);
            $table->jsonb('pack');
            $table->jsonb('new_facts')->nullable();
            $table->integer('tokens_in');
            $table->integer('tokens_out');
            $table->bigInteger('cost_micros');
            $table->jsonb('applied')->nullable();
            $table->timestamptz('created_at')->useCurrent();

            $table->index(['story_id', 'created_at'], 'ai_generations_story_created_index');
            $table->index('created_at', 'ai_generations_created_at_index');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ai_generations ADD CONSTRAINT ai_generations_kind_check CHECK (kind IN ('preedit','tags','translate','generate','en_tags'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
