<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendPortalResetPassword extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[UNB Wire] Reset Your Password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.portal-reset-password',
        );
    }
}
