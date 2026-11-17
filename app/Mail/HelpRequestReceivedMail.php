<?php

namespace App\Mail;

use App\Domain\Clients\Models\HelpRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Powiadomienie dla osoby obsługującej pomoc o nowym zgłoszeniu. */
class HelpRequestReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public HelpRequest $helpRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pomoc #{$this->helpRequest->id}: {$this->helpRequest->name}",
            replyTo: [new Address($this->helpRequest->email, $this->helpRequest->name)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.help-request-received');
    }
}
