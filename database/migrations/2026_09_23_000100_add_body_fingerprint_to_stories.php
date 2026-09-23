<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->string('body_fingerprint', 40)->nullable()->after('body_text');
            $table->index('body_fingerprint', 'stories_body_fingerprint_index');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropIndex('stories_body_fingerprint_index');
            $table->dropColumn('body_fingerprint');
        });
    }
};
