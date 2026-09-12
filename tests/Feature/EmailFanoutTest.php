<?php

namespace Tests\Feature;

use App\Jobs\FanoutStory;
use App\Jobs\SendStoryEmail;
use App\Models\Client;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailFanoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_fanout_story_dispatches_send_story_email_for_email_enabled_clients()
    {
        Queue::fake();

        $clientWithEmail = Client::factory()->create([
            'notes' => json_encode([
                'channels' => [
                    'email' => ['on' => true, 'list' => ['test@example.com']]
                ]
            ])
        ]);

        $clientWithoutEmail = Client::factory()->create([
            'notes' => json_encode([
                'channels' => [
                    'email' => ['on' => false]
                ]
            ])
        ]);
        
        $clientNoNotes = Client::factory()->create([
            'notes' => null
        ]);

        $story = Story::factory()->create(['status' => 'published']);

        (new FanoutStory($story->id))->handle();

        Queue::assertPushed(SendStoryEmail::class, function ($job) use ($clientWithEmail, $story) {
            return $job->clientId === $clientWithEmail->id && $job->storyId === $story->id;
        });

        Queue::assertNotPushed(SendStoryEmail::class, function ($job) use ($clientWithoutEmail) {
            return $job->clientId === $clientWithoutEmail->id;
        });
        
        Queue::assertNotPushed(SendStoryEmail::class, function ($job) use ($clientNoNotes) {
            return $job->clientId === $clientNoNotes->id;
        });
    }
}
