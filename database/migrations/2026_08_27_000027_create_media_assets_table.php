<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('kind', 8);
            $table->string('status', 12);
            $table->foreignId('batch_id')->nullable()->constrained('media_batches')->nullOnDelete();
            $table->string('title', 240);
            $table->text('caption');
            $table->string('credit_line', 160);
            $table->foreignId('photographer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 12)->default('staff');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('event_label', 200)->nullable();
            $table->string('location_city', 80)->nullable();
            $table->string('location_country', 80)->nullable();
            $table->timestamptz('captured_at')->nullable();
            $table->jsonb('en_tags')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->string('mime', 40);
            $table->bigInteger('size_bytes');
            $table->char('checksum', 64);
            $table->string('storage_disk', 16)->default('s3');
            $table->string('original_path', 300);
            $table->jsonb('derivatives')->default('{}');
            $table->jsonb('exif')->nullable();
            $table->timestamptz('embargo_until')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamptz('approved_at')->nullable();
            $table->integer('download_count')->default(0);
            $table->timestamptz('created_at')->useCurrent();
            $table->timestamptz('updated_at')->useCurrent();
            $table->timestamptz('deleted_at')->nullable();

            $table->index(['status', 'created_at'], 'media_assets_status_created_index');
            $table->index(['kind', 'status'], 'media_assets_kind_status_index');
            $table->index('photographer_id', 'media_assets_photographer_index');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_kind_check CHECK (kind IN ('photo','video'))");
            DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_status_check CHECK (status IN ('field','library','reedit','rejected','archived'))");
            DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_source_check CHECK (source IN ('staff','field','ap','partner'))");
            DB::statement("CREATE INDEX media_assets_library_approved_index ON media_assets (approved_at DESC) WHERE status='library'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
