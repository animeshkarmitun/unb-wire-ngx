<?php

namespace App\Mail;

use App\Models\ClientUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendPortalInvite extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClientUser $user,
        public string $plainPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[UNB Wire] Your Portal Access',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.portal-invite',
        );
    }
}
