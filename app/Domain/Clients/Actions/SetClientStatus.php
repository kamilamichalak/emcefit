<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;

final class SetClientStatus
{
    public function set(Client $client, ClientStatus $status): ClientStatus
    {
        $client->update(['status' => $status]);

        return $status;
    }

    public function toggle(Client $client): ClientStatus
    {
        return $this->set(
            $client,
            $client->status === ClientStatus::Active ? ClientStatus::Inactive : ClientStatus::Active,
        );
    }
}
