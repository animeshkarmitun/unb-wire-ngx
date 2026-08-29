<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assignments')) {
            Schema::create('assignments', function (Blueprint $table) {
                $table->id();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->jsonb('shot_list')->nullable();
                $table->string('location', 160)->nullable();
                $table->timestamptz('due_at')->nullable();
                $table->string('priority', 12)->default('routine');
                $table->string('status', 12)->default('open');
                $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamptz('created_at')->useCurrent();
                $table->timestamptz('updated_at')->useCurrent();
            });
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE assignments ADD CONSTRAINT assignments_priority_check CHECK (priority IN ('routine','urgent','flash'))");
                DB::statement("ALTER TABLE assignments ADD CONSTRAINT assignments_status_check CHECK (status IN ('open','accepted','declined','submitted','done'))");
            }
        }

        if (! Schema::hasColumn('media_batches', 'assignment_id')) {
            Schema::table('media_batches', function (Blueprint $table) {
                $table->foreignId('assignment_id')->nullable()->after('uploader_id')->constrained('assignments')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('media_batches', 'assignment_id')) {
            Schema::table('media_batches', function (Blueprint $table) {
                $table->dropConstrainedForeignId('assignment_id');
            });
        }
        Schema::dropIfExists('assignments');
    }
};
