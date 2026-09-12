<?php

namespace App\Mail;

use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StoryAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Story $story,
        public string $clientName,
        public bool $isBreaking = false,
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->isBreaking ? 'Breaking: ' : '';

        return new Envelope(
            subject: '[UNB Wire] '.$prefix.$this->story->headline,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.story-alert',
        );
    }
}
