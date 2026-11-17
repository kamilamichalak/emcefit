<?php

namespace App\Mail;

use App\Domain\Clients\Models\Client;
use App\Domain\Scheduling\Models\ClassSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/** Studio odwołało zajęcia, na które klientka była zapisana — dostaje odrobienie / zwrot. */
class ClassCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Client $client, public ClassSchedule $occurrence) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Zajęcia odwołane — wybierz inne — Studio eMCeFit');
    }

    public function content(): Content
    {
        $s = $this->occurrence->loadMissing('classGroup.classType');

        return new Content(markdown: 'mail.class-cancelled', with: [
            'firstName' => Str::before($this->client->user->name, ' '),
            'when' => Str::ucfirst($s->date->translatedFormat('l, j F')).', '.CarbonImmutable::parse($s->start_time)->format('H:i'),
            'typeName' => $s->classGroup->classType->name,
            'reason' => $s->cancellation_reason,
            'url' => route('client.free-spots.index'),
            
        ]);
    }
}
