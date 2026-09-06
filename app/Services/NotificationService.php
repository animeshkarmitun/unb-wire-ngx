<?php

namespace App\Services;

use App\Models\Story;
use App\Models\User;
use App\Notifications\StoryNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function notifyReviewRequested(int $storyId, string $headline, int $actorId): void
    {
        $this->burstNotify('review:'.$storyId, User::whereHas('role', fn ($q) => $q->whereIn('name', ['Editor', 'Admin']))->get(), 'review_requested', ['story_id' => $storyId, 'headline' => $headline, 'actor_id' => $actorId]);
    }

    public function notifyStatusChange(int $storyId, string $action, int $actorId): void
    {
        $owner = Story::find($storyId)?->owner;
        if (! $owner) {
            return;
        }
        $this->burstNotify('status:'.$storyId.':'.$action, collect([$owner]), $action, ['story_id' => $storyId, 'actor_id' => $actorId]);
    }

    private function burstNotify(string $key, $users, string $event, array $data): void
    {
        $cacheKey = 'notify_burst:'.$key;
        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, true, 300);
        Notification::send($users, new StoryNotification($event, $data));
    }
}
