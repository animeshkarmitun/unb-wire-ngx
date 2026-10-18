<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use App\Services\Delivery\WebhookPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_full_payload_for_published_story()
    {
        $owner = User::factory()->create(['name' => 'John Doe']);
        $category = Category::factory()->create(['slug' => 'politics', 'name_en' => 'Politics']);
        $tag = Tag::factory()->create(['name' => 'election']);
        $story = Story::factory()->create([
            'public_id' => 'UNB-2026-12345',
            'headline' => 'Test Headline',
            'brief' => 'Test Summary',
            'body_html' => '<p>Test Body</p>',
            'language' => 'en',
            'is_breaking' => false,
            'category_id' => $category->id,
            'owner_id' => $owner->id,
            'published_at' => '2026-09-12 22:00:00',
        ]);

        $story->tags()->attach($tag);

        $media = MediaAsset::factory()->create([
            'public_id' => 'MEDIA-123',
            'kind' => 'photo',
            'caption' => 'Test Caption',
            'derivatives' => [],
        ]);
        $story->media()->attach($media, ['caption_override' => null]);

        $builder = app(WebhookPayloadBuilder::class);
        $payload = $builder->build($story, 'story.published');

        $this->assertEquals('story.published', $payload['event']);
        $this->assertArrayHasKey('timestamp', $payload);

        $data = $payload['data'];
        $this->assertEquals('UNB-2026-12345', $data['public_id']);
        $this->assertEquals('Test Headline', $data['headline']);
        $this->assertEquals('Test Summary', $data['summary']);
        $this->assertEquals('<p>Test Body</p>', $data['body_html']);
        $this->assertEquals('en', $data['language']);
        $this->assertEquals('John Doe', $data['author']);
        $this->assertFalse($data['is_breaking']);
        $this->assertEquals(['slug' => 'politics', 'name' => 'Politics'], $data['category']);
        $this->assertEquals(['election'], $data['tags']);
        $this->assertEquals('2026-09-12T22:00:00+00:00', $data['published_at']);

        $this->assertCount(1, $data['media']);
        $this->assertEquals('MEDIA-123', $data['media'][0]['public_id']);
        $this->assertEquals('photo', $data['media'][0]['kind']);
        $this->assertEquals('Test Caption', $data['media'][0]['caption']);
        $this->assertEquals(url("/api/v1/media/{$media->id}/download"), $data['media'][0]['download_url']);
    }

    public function test_builds_payload_for_killed_story()
    {
        $story = Story::factory()->create([
            'public_id' => 'UNB-2026-KILLED',
            'headline' => 'Killed Headline',
        ]);

        $builder = app(WebhookPayloadBuilder::class);
        $payload = $builder->build($story, 'story.killed');

        $this->assertEquals('story.killed', $payload['event']);
        $this->assertEquals('UNB-2026-KILLED', $payload['data']['public_id']);
    }

    public function test_excludes_body_embedded_images_from_media_list(): void
    {
        $story = Story::factory()->create([
            'public_id' => 'UNB-2026-BODYIMG',
            'headline' => 'Body Image Story',
            'body_html' => '<p>text</p><img src="https://x/a.webp" data-media-id="7">',
        ]);

        $bodyImg = MediaAsset::factory()->create(['public_id' => 'MEDIA-BODY', 'kind' => 'photo']);
        $gallery = MediaAsset::factory()->create(['public_id' => 'MEDIA-GALLERY', 'kind' => 'photo']);
        $story->media()->attach($bodyImg, ['role' => 'body']);
        $story->media()->attach($gallery, ['role' => 'inline']);

        $payload = app(WebhookPayloadBuilder::class)->build($story->fresh(), 'story.published');

        $mediaIds = collect($payload['data']['media'])->pluck('public_id')->values()->all();
        $this->assertContains('MEDIA-GALLERY', $mediaIds);
        $this->assertNotContains('MEDIA-BODY', $mediaIds);
    }
}
