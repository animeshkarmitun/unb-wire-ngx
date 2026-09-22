<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackupFailed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $error,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[UNB Wire] Database backup failed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.backup-failed',
        );
    }
}
