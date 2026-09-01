<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Data\ClientData;
use App\Domain\Clients\Models\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class UpdateClient
{
    public function handle(Client $client, ClientData $data): Client
    {
        return DB::transaction(function () use ($client, $data): Client {
            $userAttributes = [
                'name' => $data->name,
                'email' => $data->email,
            ];

            if ($data->password !== null) {
                $userAttributes['password'] = $data->password;
            }

            $client->user->update($userAttributes);

            $client->update([
                'phone' => $data->phone,
                'birth_date' => $data->birthDate,
                'status' => $data->status,
                'join_date' => $data->joinDate,
                'terms_accepted_at' => $this->consentAt($client->terms_accepted_at, $data->termsAccepted),
                'health_declaration_at' => $this->consentAt($client->health_declaration_at, $data->healthDeclaration),
            ]);

            return $client->fresh(['user']);
        });
    }

    /**
     * Zachowuje pierwotny moment zgody; snapshot bierze "teraz" tylko przy pierwszym
     * zaznaczeniu, a odznaczenie czysci znacznik.
     */
    private function consentAt(?Carbon $current, bool $granted): ?Carbon
    {
        if (! $granted) {
            return null;
        }

        return $current ?? now();
    }
}
