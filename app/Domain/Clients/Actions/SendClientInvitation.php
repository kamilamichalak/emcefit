<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Models\Client;
use App\Mail\ClientInvitationMail;
use DomainException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

final class SendClientInvitation
{
    /** Domeny zastępczych adresów z importu — na nie nie wysyłamy. */
    private const PLACEHOLDER_DOMAINS = ['test.pl', 'example.com', 'example.org', 'example.net', 'emcefit.test'];

    /**
     * Wysyła klientce mail z jednorazowym linkiem aktywacyjnym (ważnym Client::ACTIVATION_LINK_DAYS dni)
     * i zapisuje `invitation_sent_at`.
     *
     * @throws DomainException gdy konto jest już aktywne albo adres jest zastępczy
     */
    public function handle(Client $client): void
    {
        if ($client->isActivated()) {
            throw new DomainException('Klientka ma już skonfigurowane logowanie — zaproszenie nie jest potrzebne.');
        }

        $email = $client->user->email;
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));

        if (in_array($domain, self::PLACEHOLDER_DOMAINS, true)) {
            throw new DomainException("Adres {$email} jest tymczasowy. Wpisz prawdziwy e-mail klientki i wyślij ponownie.");
        }

        $link = URL::temporarySignedRoute('client.activate.show', now()->addDays(Client::ACTIVATION_LINK_DAYS), ['client' => $client->id]);

        Mail::to($email, $client->user->name)->send(new ClientInvitationMail($client->user->name, $link));

        $client->update(['invitation_sent_at' => now()]);
    }
}
