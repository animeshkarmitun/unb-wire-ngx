<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StoryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const EMAIL_EVENTS = ['review_requested', 'changes_requested', 'killed', 'handover'];

    public function __construct(public string $event, public array $data) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (in_array($this->event, self::EMAIL_EVENTS, true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $headline = $this->data['headline'] ?? 'Untitled';

        $subject = match ($this->event) {
            'review_requested' => '[UNB Wire] Story awaiting review: '.$headline,
            'changes_requested' => '[UNB Wire] Changes requested: '.$headline,
            'killed' => '[UNB Wire] Story killed: '.$headline,
            'handover' => '[UNB Wire] Story ownership transferred: '.$headline,
            default => '[UNB Wire] Notification',
        };

        $description = match ($this->event) {
            'review_requested' => 'A story has been submitted for your review.',
            'changes_requested' => 'The desk editor has requested changes to your story.',
            'killed' => 'Your story has been killed and pulled from the wire.',
            'handover' => 'Ownership of this story has been transferred to another editor.',
            default => 'You have a new editorial notification.',
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting('UNB Wire Editorial')
            ->line($description)
            ->line('**Story:** '.$headline)
            ->action('View Story', url('/admin/news/en'))
            ->line('This is an automated notification from the UNB Wire editorial system.');
    }

    public function toArray(object $notifiable): array
    {
        return ['event' => $this->event, 'data' => $this->data, 'at' => now()->toIso8601String()];
    }
}
