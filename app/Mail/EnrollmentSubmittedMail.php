<?php

namespace App\Mail;

use App\Domain\Memberships\Models\Membership;
use App\Domain\Scheduling\Models\ClassGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/** Po zgłoszeniu na miesiąc: co wybrała, ile i dokąd zapłacić. */
class EnrollmentSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public const PAYMENT_DAYS = 3;

    public function __construct(public Membership $membership) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Zgłoszenie na '.$this->membership->start_date->translatedFormat('F').' przyjęte — Studio eMCeFit');
    }

    public function content(): Content
    {
        $m = $this->membership->loadMissing(['client.user', 'membershipType', 'classGroups.classType']);

        return new Content(markdown: 'mail.enrollment-submitted', with: [
            'firstName' => Str::before($m->client->user->name, ' '),
            'monthLabel' => $m->start_date->translatedFormat('F Y'),
            'typeName' => $m->membershipType->name,
            'price' => number_format((float) $m->price_locked, 2, ',', ' ').' zł',
            'classes' => $m->classGroups
                ->sortBy(fn (ClassGroup $g) => sprintf('%d %s', $g->weekday->value, $g->start_time))
                ->map(fn (ClassGroup $g) => $g->weekday->label().', '.$g->startsAt().'–'.$g->endsAt().' — '.$g->classType->name)
                ->values(),
            'account' => config('club.bank_account'),
            'title' => 'zajęcia fitness, '.$m->client->user->name.', '.$m->start_date->translatedFormat('F Y'),
            'deadline' => now()->addDays(self::PAYMENT_DAYS)->translatedFormat('j F'),
            'url' => route('client.classes.index', ['month' => $m->start_date->format('Y-m')]),
        ]);
    }
}
