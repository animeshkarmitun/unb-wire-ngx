<?php

namespace App\Jobs;

use App\Models\Story;
use App\Services\AiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateEnTags implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $storyId, public int $userId)
    {
        $this->onQueue('default');
    }

    public function handle(AiService $ai): void
    {
        $story = Story::find($this->storyId);
        if (! $story || $story->language !== 'bn' || ! empty($story->en_search_tags)) {
            return;
        }

        $ai->call('en_tags', [
            'text' => (string) $story->body_text,
            'headline' => (string) $story->headline,
            'language' => 'bn',
        ], $this->userId, $story->id);
    }
}
