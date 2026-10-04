<?php

namespace Tests\Feature;

use App\Jobs\FanoutStory;
use App\Livewire\Admin\AddNews;
use App\Models\Category;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * FR-AI-008 — Auto-publish allowlist gate, against the seeded defaults.
 *
 * The default `ai.desk.autoCats` is shipped via SettingSeeder and must
 * reference real seeded category names; otherwise the gate is dead.
 */
class AiAutoPublishDefaultTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SettingSeeder::class);

        $editorRole = Role::where('name', 'Editor')->first();
        $this->editor = User::factory()->create(['role_id' => $editorRole->id]);
    }

    private function defaultSettings(): array
    {
        $raw = DB::table('settings')->where('key', 'ai.desk')->value('value');
        $this->assertIsString($raw, 'ai.desk setting missing');

        return json_decode($raw, true);
    }

    public function test_default_auto_cats_resolve_to_real_seeded_categories(): void
    {
        $cfg = $this->defaultSettings();
        $this->assertIsArray($cfg['autoCats'] ?? null);
        $this->assertNotEmpty($cfg['autoCats']);

        $realNames = Category::pluck('name_en')->all();

        // At least one default autoCat must match a real seeded category; otherwise
        // the gate is permanently disabled in a default install.
        $overlap = array_intersect($cfg['autoCats'], $realNames);
        $this->assertNotEmpty($overlap, 'default autoCats '.json_encode($cfg['autoCats']).' does not match any seeded category '.json_encode($realNames));
    }

    public function test_auto_publish_publishes_when_default_allowlist_matches(): void
    {
        Queue::fake();

        // Default config from SettingSeeder; do NOT override autoCats. Only flip
        // autoPublish=true to simulate the desk editor enabling the feature.
        $cfg = $this->defaultSettings();
        $cfg['autoPublish'] = true;
        $cfg['autoCats'] = $cfg['autoCats'] ?? [];
        DB::table('settings')->where('key', 'ai.desk')->update(['value' => json_encode($cfg)]);
        DB::table('ai_token_usage_daily')->delete();

        $overlap = array_values(array_intersect($cfg['autoCats'], Category::pluck('name_en')->all()));
        $this->assertNotEmpty($overlap, 'no overlap between defaults and seeded categories');
        $cat = Category::whereIn('name_en', $overlap)->firstOrFail();

        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Auto publish via default allowlist')
            ->set('brief', 'Brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $cat->id)
            ->call('autosave');

        $storyId = $cmp->get('storyId');
        $this->assertNotNull($storyId);

        $cmp->call('callAi', 'preedit');
        $pack = $cmp->get('aiPack');
        $this->assertNotNull($pack, 'AI pack should not be null');
        $this->assertArrayNotHasKey('error', $pack, 'AI returned error: '.json_encode($pack));

        $cmp->call('applyAi', 'headline');
        $cmp->call('publish');

        $story = Story::find($storyId);
        $this->assertSame('published', $story->status);

        $this->assertDatabaseHas('story_events', [
            'story_id' => $storyId,
            'action' => 'auto_published',
        ]);

        $payload = DB::table('story_events')
            ->where('story_id', $storyId)
            ->where('action', 'auto_published')
            ->value('payload');
        $decoded = is_string($payload) ? json_decode($payload, true) : $payload;
        $this->assertSame('auto', $decoded['gate'] ?? null);
        $this->assertSame('skipped_auto_allowlist', $decoded['ai_gate'] ?? null);

        Queue::assertPushed(FanoutStory::class);
    }
}
