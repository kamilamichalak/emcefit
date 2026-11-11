<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Models\Client;
use App\Mail\ClientPasswordResetMail;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

final class SendPasswordResetLink
{
    /** Ile godzin ważny jest link do resetu hasła wysłany mailem. */
    public const LINK_HOURS = 24;

    public function __construct(private SendClientInvitation $sendClientInvitation) {}

    /**
     * „Nie pamiętam hasła”: klientka z aktywnym kontem dostaje link do ustawienia nowego hasła,
     * klientka bez aktywnego konta — zaproszenie z linkiem aktywacyjnym.
     * Nieznany adres, adres zastępczy (@test.pl) i konta niebędące klientkami — nic nie wysyłamy.
     * Wywołujący zawsze pokazuje ten sam komunikat, żeby nie zdradzać, czy adres jest w systemie.
     */
    public function handle(string $email): void
    {
        $email = mb_strtolower(trim($email));

        if (SendClientInvitation::isPlaceholderEmail($email)) {
            return;
        }

        $client = User::query()->where('email', $email)->first()?->client;

        if (! $client instanceof Client || ! $client->user->hasRole('client')) {
            return;
        }

        if (! $client->isActivated()) {
            try {
                $this->sendClientInvitation->handle($client);
            } catch (DomainException $e) {
                Log::info('Reset hasła: nie wysłano zaproszenia', ['client_id' => $client->id, 'reason' => $e->getMessage()]);
            }

            return;
        }

        $link = URL::temporarySignedRoute(
            'client.password-reset.show',
            now()->addHours(self::LINK_HOURS),
            ['client' => $client->id],
        );

        Mail::to($client->user->email, $client->user->name)
            ->send(new ClientPasswordResetMail($client->user->name, $link));
    }
}
