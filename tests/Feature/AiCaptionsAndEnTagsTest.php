<?php

namespace Tests\Feature;

use App\Jobs\GenerateEnTags;
use App\Jobs\ProcessIndexOutbox;
use App\Livewire\Admin\AddNews;
use App\Livewire\Admin\PhotoManager;
use App\Models\AiGeneration;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Services\AiService;
use App\Services\StoryService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AiCaptionsAndEnTagsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SettingSeeder::class);
        $editorRole = Role::where('name', 'Editor')->first();
        $this->user = User::factory()->create(['role_id' => $editorRole?->id]);
        $this->cat = Category::first();
        DB::table('ai_token_usage_daily')->delete();
    }

    private function makeAsset(): MediaAsset
    {
        return MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Photo title',
            'caption' => 'Old caption',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 10,
            'checksum' => hash('sha256', Str::random()),
            'storage_disk' => 'public',
            'original_path' => 'media/uploads/x.jpg',
            'uploaded_by' => $this->user->id,
        ]);
    }

    // ─── FR-MED-011: photo caption/tags suggestions ──────────────

    public function test_photo_ai_suggestions_respect_photos_toggle(): void
    {
        $asset = $this->makeAsset();
        DB::table('settings')->where('key', 'ai.desk')->update([
            'value' => json_encode(['preeditPhotos' => false, 'killed' => false, 'monthlyCap' => 500000, 'preeditEn' => true]),
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(PhotoManager::class)
            ->call('selectAsset', $asset->id)
            ->call('suggestAiMetadata');

        $this->assertSame([], $component->get('aiSuggestions'));
    }

    public function test_photo_ai_suggestions_and_confirm_apply(): void
    {
        $asset = $this->makeAsset();
        DB::table('settings')->where('key', 'ai.desk')->update([
            'value' => json_encode(['preeditPhotos' => true, 'killed' => false, 'monthlyCap' => 500000, 'preeditEn' => true]),
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(PhotoManager::class)
            ->call('selectAsset', $asset->id)
            ->call('suggestAiMetadata');

        $suggestions = $component->get('aiSuggestions');
        $this->assertNotEmpty($suggestions['caption']);
        $this->assertNotEmpty($suggestions['tags']);

        $component->call('applyAiSuggestion', 'caption')
            ->assertSet('inspCaption', $suggestions['caption']);
        $component->call('applyAiSuggestion', 'tags')
            ->assertSet('inspKeywords', implode(', ', $suggestions['tags']));
    }

    // ─── FR-AI-010: Bangla story English search tags ─────────────

    public function test_bn_story_dispatches_en_tags_job(): void
    {
        Queue::fake();

        app(StoryService::class)->createDraft([
            'language' => 'bn',
            'headline' => 'নতুন সংবাদ',
            'brief' => 'সংক্ষিপ্ত',
            'body_html' => '<p>বাংলা মূল পাঠ।</p>',
            'category_id' => $this->cat->id,
        ], $this->user);

        Queue::assertPushed(GenerateEnTags::class);
    }

    public function test_en_tags_job_records_ai_generation_suggestions(): void
    {
        $story = Story::factory()->create(['language' => 'bn', 'category_id' => $this->cat->id, 'status' => 'draft']);

        (new GenerateEnTags($story->id, $this->user->id))->handle(app(AiService::class));

        $row = AiGeneration::where('story_id', $story->id)->where('kind', 'en_tags')->first();
        $this->assertNotNull($row);
        $this->assertNotEmpty($row->pack['tags']);
    }

    public function test_confirm_en_tags_persists_search_tags_and_feeds_index(): void
    {
        $cmp = Livewire::actingAs($this->user)
            ->test(AddNews::class)
            ->set('headline', 'Bangla headline')
            ->set('brief', 'Brief')
            ->set('bodyHtml', '<p>বাংলা পাঠ।</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->set('language', 'bn')
            ->call('autosave');

        $storyId = $cmp->get('storyId');
        DB::table('ai_generations')->insert([
            'story_id' => $storyId,
            'user_id' => $this->user->id,
            'kind' => 'en_tags',
            'prompt_version' => 'v1',
            'model' => 'stub',
            'input_hash' => hash('sha256', 'x'),
            'pack' => json_encode(['tags' => ['bangladesh', 'metro']]),
            'tokens_in' => 1,
            'tokens_out' => 1,
            'cost_micros' => 1,
            'created_at' => now(),
        ]);

        $cmp->call('autosave');
        $this->assertSame(['bangladesh', 'metro'], $cmp->get('enTagSuggestions'));

        $cmp->call('confirmEnTags');

        $story = Story::find($storyId);
        $this->assertSame(['bangladesh', 'metro'], $story->en_search_tags);

        DB::table('index_outbox')->insert([
            'index_name' => 'main', 'op' => 'upsert', 'document_id' => $story->public_id,
            'status' => 'pending', 'attempts' => 0, 'created_at' => now(),
        ]);
        (new ProcessIndexOutbox)->handle();
        $this->assertTrue(true);
    }

    public function test_outbox_payload_contains_search_tags(): void
    {
        $story = Story::factory()->create([
            'language' => 'bn',
            'status' => 'published',
            'category_id' => $this->cat->id,
            'en_search_tags' => ['dhaka', 'metro'],
        ]);
        DB::table('index_outbox')->insert([
            'index_name' => 'main', 'op' => 'upsert', 'document_id' => $story->public_id,
            'status' => 'pending', 'attempts' => 0, 'created_at' => now(),
        ]);

        $row = DB::table('index_outbox')->where('document_id', $story->public_id)->first();
        $method = new \ReflectionMethod(ProcessIndexOutbox::class, 'buildPayload');
        $method->setAccessible(true);
        $payload = $method->invoke(new ProcessIndexOutbox, $row, $story);

        $this->assertSame(['dhaka', 'metro'], $payload['search_tags']);
    }
}
