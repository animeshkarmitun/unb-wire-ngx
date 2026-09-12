<?php

namespace App\Events;

use App\Models\Story;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StoryPublished implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Story $story) {}

    public function broadcastOn(): array
    {
        return [new Channel('wire.' . $this->story->language)];
    }

    public function broadcastWith(): array
    {
        return [
            'public_id' => $this->story->public_id,
            'headline' => $this->story->headline,
            'summary' => $this->story->brief,
            'category' => $this->story->category?->name ?? $this->story->category?->slug,
            'is_breaking' => (bool) $this->story->is_breaking,
            'published_at' => now()->toIso8601String(),
        ];
    }
}
