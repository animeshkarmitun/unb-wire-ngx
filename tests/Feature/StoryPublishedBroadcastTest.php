<?php

namespace Tests\Feature;

use App\Events\StoryPublished;
use App\Models\Story;
use App\Models\User;
use App\Services\StoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StoryPublishedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_story_published_event_is_dispatched()
    {
        Event::fake();

        $user = User::factory()->create();
        $story = Story::factory()->create([
            'status' => 'draft',
            'language' => 'en',
        ]);

        $service = app(StoryService::class);
        $service->transition($story, 'in_review', $user);
        $service->transition($story, 'approved', $user);
        $service->transition($story, 'published', $user);

        Event::assertDispatched(StoryPublished::class, function ($event) use ($story) {
            return $event->story->id === $story->id;
        });
    }
}
