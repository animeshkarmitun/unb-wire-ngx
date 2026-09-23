<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->index(['deliverable_type', 'deliverable_id'], 'deliveries_deliverable_index');
        });
        Schema::table('client_channels', function (Blueprint $table) {
            $table->index('client_id', 'client_channels_client_index');
        });
        Schema::table('client_api_keys', function (Blueprint $table) {
            $table->index('client_id', 'client_api_keys_client_index');
        });
        Schema::table('client_packages', function (Blueprint $table) {
            $table->index('package_id', 'client_packages_package_index');
        });
        Schema::table('story_media', function (Blueprint $table) {
            $table->index('asset_id', 'story_media_asset_index');
        });
        Schema::table('story_tag', function (Blueprint $table) {
            $table->index('tag_id', 'story_tag_tag_index');
        });
        Schema::table('media_tag', function (Blueprint $table) {
            $table->index('tag_id', 'media_tag_tag_index');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', fn (Blueprint $table) => $table->dropIndex('deliveries_deliverable_index'));
        Schema::table('client_channels', fn (Blueprint $table) => $table->dropIndex('client_channels_client_index'));
        Schema::table('client_api_keys', fn (Blueprint $table) => $table->dropIndex('client_api_keys_client_index'));
        Schema::table('client_packages', fn (Blueprint $table) => $table->dropIndex('client_packages_package_index'));
        Schema::table('story_media', fn (Blueprint $table) => $table->dropIndex('story_media_asset_index'));
        Schema::table('story_tag', fn (Blueprint $table) => $table->dropIndex('story_tag_tag_index'));
        Schema::table('media_tag', fn (Blueprint $table) => $table->dropIndex('media_tag_tag_index'));
    }
};
