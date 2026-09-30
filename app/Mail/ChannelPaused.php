<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChannelPaused extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $clientName,
        public string $channelType,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[UNB Wire] Delivery channel auto-paused',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.channel-paused',
        );
    }
}
