<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Data\ClientData;
use App\Domain\Clients\Models\Client;
use Illuminate\Support\Facades\DB;

final class UpdateClient
{
    /**
     * Aktualizuje dane podstawowe klienta. Status, haslo i zgody nie sa tu ruszane
     * (status — przez przelacznik na liscie; haslo/zgody — przez klienta).
     */
    public function handle(Client $client, ClientData $data): Client
    {
        return DB::transaction(function () use ($client, $data): Client {
            $client->user->update([
                'name' => $data->name,
                'email' => $data->email,
            ]);

            $client->update([
                'phone' => $data->phone,
                'birth_date' => $data->birthDate,
            ]);

            return $client->fresh(['user']);
        });
    }
}
