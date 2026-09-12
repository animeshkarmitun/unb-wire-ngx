<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Story;
use App\Mail\StoryAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendStoryEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $storyId,
        public int $clientId
    ) {
        $this->onQueue('fanout');
    }

    public function handle(): void
    {
        $client = Client::find($this->clientId);
        $story = Story::with(['category', 'tags', 'media'])->find($this->storyId);

        if (!$client || !$story) {
            return;
        }

        $notes = is_string($client->notes) ? json_decode($client->notes, true) : ($client->notes ?? []);
        $emailConfig = $notes['channels']['email'] ?? [];

        // Guard: email must be enabled
        if (empty($emailConfig['on'])) {
            return;
        }

        // Alert preference matching (same OR logic as TriggerMatcher)
        $alerts = $emailConfig['alerts'] ?? [];
        $activeAlerts = array_filter($alerts);
        
        if (!empty($activeAlerts)) {
            $match = false;
            
            if (!empty($alerts['breaking']) && $story->is_breaking) {
                $match = true;
            }
            if (!empty($alerts['media_pack']) && $story->media->isNotEmpty()) {
                $match = true;
            }
            if (!empty($alerts['exclusive']) && ($story->getAttribute('is_exclusive') || $story->tags->contains('slug', 'exclusive'))) {
                $match = true;
            }
            // TriggerMatcher also has embargoed but user request explicitly listed breaking, media_pack, exclusive, saved_search.
            // I'll stick to what the user requested for SendStoryEmail alert checking logic.
            // User request says: 
            // if (!empty($alerts['breaking']) && $story->is_breaking) $match = true;
            // if (!empty($alerts['media_pack']) && $story->media->isNotEmpty()) $match = true;
            // if (!empty($alerts['exclusive']) && $story->is_exclusive) $match = true;
            
            if (!$match) {
                return; // Story doesn't match any active alert preference
            }
        }
        // If no alerts configured → send all stories (backward compat)

        // Send to all recipients
        $recipients = $emailConfig['list'] ?? [];
        foreach ($recipients as $email) {
            Mail::to($email)->queue(new StoryAlert($story, $client->name, (bool)$story->is_breaking));
        }
    }
}
