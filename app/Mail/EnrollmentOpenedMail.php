<?php

namespace App\Mail;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/** Studio otworzyło zapisy na kolejny miesiąc. */
class EnrollmentOpenedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public CarbonImmutable $month) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Zapisy na '.$this->month->translatedFormat('F').' są otwarte — Studio eMCeFit');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.enrollment-opened', with: [
            'firstName' => Str::before($this->name, ' '),
            'monthLabel' => $this->month->translatedFormat('F'),
            'url' => route('client.enrollment.create', ['month' => $this->month->format('Y-m')]),
        ]);
    }
}
