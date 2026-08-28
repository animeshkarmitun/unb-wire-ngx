<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_batches', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $table->string('event_label', 200);
            $table->string('urgency', 12)->default('routine');
            $table->string('status', 16)->default('pending');
            $table->timestamptz('submitted_at')->useCurrent();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamptz('reviewed_at')->nullable();
            $table->timestamptz('created_at')->useCurrent();
            $table->timestamptz('updated_at')->useCurrent();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE media_batches ADD CONSTRAINT media_batches_urgency_check CHECK (urgency IN ('routine','urgent','flash'))");
            DB::statement("ALTER TABLE media_batches ADD CONSTRAINT media_batches_status_check CHECK (status IN ('pending','partial','reviewed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_batches');
    }
};
