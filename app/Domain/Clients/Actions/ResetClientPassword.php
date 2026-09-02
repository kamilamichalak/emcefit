<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Models\Client;

final class ResetClientPassword
{
    /**
     * Ustawia nowe hasło klienta z podpisanego linku (Prompt 18). Zgód nie ruszamy —
     * były już zaakceptowane przy aktywacji konta.
     */
    public function handle(Client $client, string $password): Client
    {
        // plain — cast 'hashed' na modelu User zahaszuje przy zapisie
        $client->user->update(['password' => $password]);

        return $client->fresh('user');
    }
}
