<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClientStoryNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $event,
        public array $data
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'title' => $this->data['title'] ?? 'UNB Wire Notification',
            'message' => $this->data['message'] ?? '',
            'story_id' => $this->data['story_id'] ?? null,
            'public_id' => $this->data['public_id'] ?? null,
            'category' => $this->data['category'] ?? null,
            'urgency' => $this->data['urgency'] ?? 'regular',
            'created_at' => now()->toIso8601String(),
        ];
    }
}
