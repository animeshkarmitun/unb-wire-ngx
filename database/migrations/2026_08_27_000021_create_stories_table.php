<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('language', 2);
            $table->foreignId('mirror_of_id')->nullable()->constrained('stories')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('headline', 300);
            $table->string('sub_head', 300)->nullable();
            $table->string('brief', 280);
            $table->text('body_html');
            $table->text('body_text');
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('sub_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('dateline_city', 80)->nullable();
            $table->timestamptz('dateline_at')->nullable();
            $table->timestamptz('published_at')->nullable();
            $table->timestamptz('embargo_until')->nullable();
            $table->boolean('is_breaking')->default(false);
            $table->string('priority', 12)->default('routine');
            $table->string('source', 12)->default('desk');
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamptz('locked_at')->nullable();
            $table->bigInteger('version')->default(1);
            $table->jsonb('ai_touched')->nullable();
            $table->integer('word_count')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamptz('created_at')->useCurrent();
            $table->timestamptz('updated_at')->useCurrent();
            $table->timestamptz('deleted_at')->nullable();

            $table->index(['status', 'published_at'], 'stories_status_published_at_index');
            $table->index(['language', 'status'], 'stories_language_status_index');
            $table->index(['category_id', 'status'], 'stories_category_status_index');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE stories ADD CONSTRAINT stories_language_check CHECK (language IN ('en','bn'))");
            DB::statement("ALTER TABLE stories ADD CONSTRAINT stories_status_check CHECK (status IN ('draft','in_review','changes_requested','approved','published','archived','killed'))");
            DB::statement("ALTER TABLE stories ADD CONSTRAINT stories_priority_check CHECK (priority IN ('routine','urgent','flash'))");
            DB::statement("ALTER TABLE stories ADD CONSTRAINT stories_source_check CHECK (source IN ('desk','mojo','ai','wire'))");
            DB::statement("CREATE INDEX stories_owner_workload_index ON stories (owner_id) WHERE status NOT IN ('published','archived')");
            DB::statement("CREATE INDEX stories_published_feed_index ON stories (published_at DESC) WHERE status='published'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};
