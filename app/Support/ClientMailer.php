<?php

namespace App\Support;

use App\Domain\Clients\Actions\SendClientInvitation;
use App\Domain\Clients\Models\Client;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Wysyłka powiadomień do klientek. Nie wysyła na adresy zastępcze (@test.pl itp.)
 * i nigdy nie blokuje akcji — błąd wysyłki tylko loguje.
 */
final class ClientMailer
{
    public static function send(Client $client, Mailable $mailable): bool
    {
        $email = $client->user->email;

        if (SendClientInvitation::isPlaceholderEmail($email)) {
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
