<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamptz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_events');
    }
};
