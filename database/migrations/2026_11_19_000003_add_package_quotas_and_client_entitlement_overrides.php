<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedInteger('stories_quota')->nullable();
            $table->unsignedInteger('media_quota')->nullable();
        });

        Schema::create('client_entitlement_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->boolean('override_stories_quota')->default(false);
            $table->unsignedInteger('stories_quota')->nullable();
            $table->boolean('override_media_quota')->default(false);
            $table->unsignedInteger('media_quota')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamptz('created_at')->useCurrent();
            $table->timestamptz('updated_at')->useCurrent();
        });

        DB::table('packages')->where('code', 'PREMIUM-BUNDLE')->update([
            'stories_quota' => 2000,
            'media_quota' => 1000,
        ]);
        DB::table('packages')->where('code', 'STANDARD-NEWS')->update([
            'stories_quota' => 800,
            'media_quota' => 400,
        ]);
        DB::table('packages')->where('code', 'BASIC-NEWS')->update([
            'stories_quota' => 200,
            'media_quota' => 0,
        ]);
        DB::table('packages')->where('code', 'DISTRICT-NEWS')->update([
            'stories_quota' => 200,
            'media_quota' => 0,
        ]);

        // Preserve existing per-client onboarding agreements as explicit overrides.
        foreach (DB::table('clients')->select('id', 'notes')->whereNotNull('notes')->get() as $client) {
            $notes = is_string($client->notes) ? json_decode($client->notes, true) : $client->notes;
            $quotas = is_array($notes) ? ($notes['tier_quotas'] ?? null) : null;
            if (! is_array($quotas) || $quotas === []) {
                continue;
            }

            $hasStories = array_key_exists('stories_quota', $quotas);
            $hasMedia = array_key_exists('media_quota', $quotas);
            if (! $hasStories && ! $hasMedia) {
                continue;
            }

            DB::table('client_entitlement_overrides')->insert([
                'client_id' => $client->id,
                'override_stories_quota' => $hasStories,
                'stories_quota' => $hasStories && $quotas['stories_quota'] !== null ? max(0, (int) $quotas['stories_quota']) : null,
                'override_media_quota' => $hasMedia,
                'media_quota' => $hasMedia && $quotas['media_quota'] !== null ? max(0, (int) $quotas['media_quota']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Owner-approved Basic package: full text + story-attached photos are visible;
        // media_quota=0 still blocks original downloads independently.
        $basic = DB::table('packages')->where('code', 'BASIC-NEWS')->first();
        if ($basic) {
            $filter = is_string($basic->entitlement_filter) ? json_decode($basic->entitlement_filter, true) : (array) $basic->entitlement_filter;
            $filter['media_kinds'] = ['photo'];
            $filter['wire'] = 'Full text + accompanying images';
            $filter['quota'] = '200 stories · no original-media downloads';
            DB::table('packages')->where('id', $basic->id)->update([
                'description' => 'Full-text wire with story-accompanying images; original media downloads are not included.',
                'entitlement_filter' => json_encode($filter),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_entitlement_overrides');
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['stories_quota', 'media_quota']);
        });
    }
};
