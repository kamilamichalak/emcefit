<?php

namespace App\Mail;

use App\Domain\Reservations\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/** Zwolniło się miejsce i klientka wskoczyła z listy oczekujących. */
class WaitlistPromotedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Zwolniło się miejsce — jesteś zapisana! — Studio eMCeFit');
    }

    public function content(): Content
    {
        $r = $this->reservation->loadMissing(['client.user', 'classSchedule.classGroup.classType']);
        $s = $r->classSchedule;

        return new Content(markdown: 'mail.waitlist-promoted', with: [
            'firstName' => Str::before($r->client->user->name, ' '),
            'when' => Str::ucfirst($s->date->translatedFormat('l, j F')).', '.CarbonImmutable::parse($s->start_time)->format('H:i'),
            'typeName' => $s->classGroup->classType->name,
            'url' => route('client.classes.index', ['month' => $s->date->format('Y-m')]),
        ]);
    }
}
