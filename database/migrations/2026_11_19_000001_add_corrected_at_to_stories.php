<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COS-77: marks when a published story was corrected so subscriber newsrooms
 * can tell the text changed after they may already have used it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->timestampTz('corrected_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('corrected_at');
        });
    }
};
