<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->index(['language', 'status', 'updated_at'], 'stories_language_status_updated_at_index');
            $table->index(['status', 'updated_at'], 'stories_status_updated_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropIndex('stories_language_status_updated_at_index');
            $table->dropIndex('stories_status_updated_at_index');
        });
    }
};
