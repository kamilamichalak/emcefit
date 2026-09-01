<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Data\ClientData;
use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateClient
{
    /**
     * Tworzy konto uzytkownika (rola: client) wraz z kartoteka klienta.
     * Klient startuje jako NIEAKTYWNY — haslo, zgody i status "aktywny" ustawia
     * sam przez link aktywacyjny (Prompt 9).
     */
    public function handle(ClientData $data): Client
    {
        return DB::transaction(function () use ($data): Client {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                // tymczasowe losowe haslo — klient ustawi wlasne przy aktywacji
                'password' => Str::password(24),
            ]);

            $user->assignRole('client');

            return $user->client()->create([
                'phone' => $data->phone,
                'birth_date' => $data->birthDate,
                'status' => ClientStatus::Inactive,
                'join_date' => now()->toDateString(),
            ]);
        });
    }
}
