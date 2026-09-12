<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Story;
use App\Models\Tag;
use App\Services\Delivery\TriggerMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TriggerMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_fires_when_no_triggers_configured()
    {
        $matcher = new TriggerMatcher;
        $story = Story::factory()->create(['is_breaking' => false, 'embargo_until' => null]);

        $this->assertTrue($matcher->shouldFire($story, []));
        $this->assertTrue($matcher->shouldFire($story, ['breaking' => false, 'media_pack' => false]));
    }

    public function test_fires_on_breaking_trigger()
    {
        $matcher = new TriggerMatcher;

        $breakingStory = Story::factory()->create(['is_breaking' => true]);
        $normalStory = Story::factory()->create(['is_breaking' => false]);

        $triggers = ['breaking' => true];

        $this->assertTrue($matcher->shouldFire($breakingStory, $triggers));
        $this->assertFalse($matcher->shouldFire($normalStory, $triggers));
    }

    public function test_fires_on_media_pack_trigger()
    {
        $matcher = new TriggerMatcher;

        $storyWithMedia = Story::factory()->create();
        $storyWithMedia->media()->attach(MediaAsset::factory()->create(['derivatives' => []]));

        $storyWithoutMedia = Story::factory()->create();

        $triggers = ['media_pack' => true];

        $this->assertTrue($matcher->shouldFire($storyWithMedia, $triggers));
        $this->assertFalse($matcher->shouldFire($storyWithoutMedia, $triggers));
    }

    public function test_fires_on_exclusive_trigger()
    {
        $matcher = new TriggerMatcher;

        $exclusiveStory = Story::factory()->create();
        $tag = Tag::factory()->create(['slug' => 'exclusive']);
        $exclusiveStory->tags()->attach($tag);

        $normalStory = Story::factory()->create();

        $triggers = ['exclusive' => true];

        $this->assertTrue($matcher->shouldFire($exclusiveStory, $triggers));
        $this->assertFalse($matcher->shouldFire($normalStory, $triggers));
    }

    public function test_fires_on_embargoed_trigger()
    {
        $matcher = new TriggerMatcher;

        $embargoedStory = Story::factory()->create(['embargo_until' => now()->addHour()]);
        $normalStory = Story::factory()->create(['embargo_until' => null]);

        $triggers = ['embargoed' => true];

        $this->assertTrue($matcher->shouldFire($embargoedStory, $triggers));
        $this->assertFalse($matcher->shouldFire($normalStory, $triggers));
    }

    public function test_fires_on_multiple_triggers_using_or_logic()
    {
        $matcher = new TriggerMatcher;

        // Has media but is NOT breaking
        $story = Story::factory()->create(['is_breaking' => false]);
        $story->media()->attach(MediaAsset::factory()->create(['derivatives' => []]));

        // Triggers require breaking OR media_pack
        $triggers = [
            'breaking' => true,
            'media_pack' => true,
        ];

        $this->assertTrue($matcher->shouldFire($story, $triggers));
    }
}
