<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->bigInteger('version');
            $table->jsonb('snapshot');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamptz('created_at')->useCurrent();

            $table->unique(['story_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_versions');
    }
};
