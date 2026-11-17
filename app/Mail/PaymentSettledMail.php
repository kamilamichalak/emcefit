<?php

namespace App\Mail;

use App\Domain\Memberships\Models\Membership;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/** Po zaksięgowaniu wpłaty: miejsca potwierdzone (i ewentualnie lista oczekujących). */
class PaymentSettledMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $waitlisted  zajęcia, na które zabrakło miejsca (lista oczekujących)
     */
    public function __construct(public Membership $membership, public array $waitlisted = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Wpłata zaksięgowana — Studio eMCeFit');
    }

    public function content(): Content
    {
        $m = $this->membership->loadMissing(['client.user', 'membershipType']);

        return new Content(markdown: 'mail.payment-settled', with: [
            'firstName' => Str::before($m->client->user->name, ' '),
            'monthLabel' => $m->start_date->translatedFormat('F Y'),
            'typeName' => $m->membershipType->name,
            'url' => route('client.classes.index', ['month' => $m->start_date->format('Y-m')]),
        ]);
    }
}
