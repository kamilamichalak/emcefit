<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use Illuminate\Support\Facades\DB;

final class ActivateClientAccount
{
    /**
     * Aktywuje konto klienta z linku jednorazowego: ustawia haslo, znaczniki zgody
     * (regulamin + oswiadczenie zdrowotne) oraz `invitation_used_at` — od tej pory
     * link jest nieaktywny.
     */
    public function handle(Client $client, string $password): Client
    {
        return DB::transaction(function () use ($client, $password): Client {
            // plain — cast 'hashed' na modelu User zahaszuje przy zapisie
            $client->user->update(['password' => $password]);

            $now = now();
            $client->update([
                'terms_accepted_at' => $client->terms_accepted_at ?? $now,
                'health_declaration_at' => $client->health_declaration_at ?? $now,
                'invitation_used_at' => $now,
                'status' => ClientStatus::Active,
            ]);

            return $client->fresh('user');
        });
    }
}
