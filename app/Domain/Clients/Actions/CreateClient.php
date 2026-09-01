<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Data\ClientData;
use App\Domain\Clients\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateClient
{
    /**
     * Tworzy konto uzytkownika (rola: client) wraz z kartoteka klienta.
     */
    public function handle(ClientData $data): Client
    {
        return DB::transaction(function () use ($data): Client {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                // plain — cast 'hashed' na modelu User zahaszuje przy zapisie
                'password' => $data->password ?? Str::password(16),
            ]);

            $user->assignRole('client');

            return $user->client()->create([
                'phone' => $data->phone,
                'birth_date' => $data->birthDate,
                'status' => $data->status,
                'join_date' => $data->joinDate ?? now()->toDateString(),
                'terms_accepted_at' => $data->termsAccepted ? now() : null,
                'health_declaration_at' => $data->healthDeclaration ? now() : null,
            ]);
        });
    }
}
