<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Zaproszenie do zapisów na zajęcia w eMCeFit');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.client-invitation');
    }
}
