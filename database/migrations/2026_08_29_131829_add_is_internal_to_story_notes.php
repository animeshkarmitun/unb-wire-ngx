<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('story_notes', function (Blueprint $table) {
            $table->boolean('is_internal')->default(true)->after('kind');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('story_notes', function (Blueprint $table) {
            $table->dropColumn('is_internal');
        });
    }
};
