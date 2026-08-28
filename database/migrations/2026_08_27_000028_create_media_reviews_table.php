<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 12);
            $table->string('reason_code', 40)->nullable();
            $table->text('note')->nullable();
            $table->timestamptz('created_at')->useCurrent();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE media_reviews ADD CONSTRAINT media_reviews_action_check CHECK (action IN ('approve','reject','reedit'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_reviews');
    }
};
