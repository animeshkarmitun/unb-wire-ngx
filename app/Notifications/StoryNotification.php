<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StoryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $event, public array $data) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        return ['event'=>$this->event,'data'=>$this->data,'at'=>now()->toIso8601String()];
    }
}
