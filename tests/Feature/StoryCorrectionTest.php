<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientApiKey;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Services\StoryService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * COS-77: corrections on published stories are marked and exposed to clients.
 */
class StoryCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->editor = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);
        $this->cat = Category::first() ?? Category::factory()->create();
    }

    private function publishedStory(): Story
    {
        return Story::factory()->create([
            'status' => 'published',
            'language' => 'en',
            'category_id' => $this->cat->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
            'published_at' => now()->subDay(),
            'headline' => 'Correction probe',
            'body_html' => '<p>Original text with 40 desks.</p>',
        ]);
    }

    public function test_editing_published_story_sets_corrected_at(): void
    {
        $story = $this->publishedStory();
        $this->assertNull($story->corrected_at);

        app(StoryService::class)->updateDraft($story, [
            'headline' => 'Correction probe',
            'body_html' => '<p>Updated text with 45 desks.</p>',
        ], $story->version, $this->editor);

        $story->refresh();
        $this->assertNotNull($story->corrected_at);
        $this->assertTrue($story->corrected_at->isAfter($story->published_at));
    }

    public function test_unpublished_edits_do_not_set_corrected_at(): void
    {
        $story = Story::factory()->create([
            'status' => 'draft',
            'language' => 'en',
            'category_id' => $this->cat->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);

        app(StoryService::class)->updateDraft($story, ['headline' => 'Draft tweak'], $story->version, $this->editor);

        $story->refresh();
        $this->assertNull($story->corrected_at);
    }

    public function test_portal_payloads_expose_correction_flag(): void
    {
        $client = Client::factory()->create();
        $raw = 'unb_live_'.Str::random(16);
        ClientApiKey::create([
            'client_id' => $client->id,
            'name' => 'k',
            'key_hash' => hash('sha256', $raw),
            'scopes' => json_encode(['feed:read']),
            'rate_limit_rpm' => 60,
        ]);
        $auth = ['Authorization' => 'Bearer '.$raw];

        $story = $this->publishedStory();
        app(StoryService::class)->updateDraft($story, [
            'headline' => 'Correction probe',
            'body_html' => '<p>Updated text with 45 desks.</p>',
        ], $story->version, $this->editor);

        $feed = $this->getJson('/api/v1/portal/feed?limit=20', $auth)->assertOk();
        $item = collect($feed->json('data'))->firstWhere('public_id', $story->public_id);
        $this->assertNotNull($item);
        $this->assertTrue($item['is_corrected']);
        $this->assertNotNull($item['corrected_at']);

        $detail = $this->getJson("/api/v1/portal/story/{$story->public_id}", $auth)->assertOk();
        $this->assertTrue($detail->json('data.is_corrected'));
    }
}
