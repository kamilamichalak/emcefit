<?php

namespace App\Support;

use App\Domain\Clients\Actions\SendClientInvitation;
use App\Domain\Clients\Models\Client;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Wysyłka powiadomień do klientek. Nie wysyła na adresy zastępcze (@test.pl itp.),
 * do kont nieaktywowanych ani gdy klientka wyłączyła dany rodzaj maili ($preference).
 * Nigdy nie blokuje akcji — błąd wysyłki tylko loguje.
 */
final class ClientMailer
{
    public static function send(Client $client, Mailable $mailable, ?string $preference = null): bool
    {
        $email = $client->user->email;

        if (SendClientInvitation::isPlaceholderEmail($email)
            || ! $client->isActivated()
            || ($preference !== null && ! $client->wantsMail($preference))) {
            return false;
        }

        try {
            Mail::to($email, $client->user->name)->send($mailable);

            return true;
        } catch (Throwable $e) {
            Log::error('Nie udało się wysłać maila do klientki', [
                'client_id' => $client->id,
                'mail' => $mailable::class,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
