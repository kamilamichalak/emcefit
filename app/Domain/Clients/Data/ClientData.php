<?php

namespace App\Domain\Clients\Data;

use App\Domain\Clients\Enums\ClientStatus;

/**
 * Zestaw danych klienta przekazywany z warstwy HTTP do akcji domenowych.
 * Daty jako stringi 'Y-m-d' (Eloquent castuje przy zapisie).
 */
final readonly class ClientData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password,
        public ?string $phone,
        public ?string $birthDate,
        public ClientStatus $status,
        public ?string $joinDate,
        public bool $termsAccepted,
        public bool $healthDeclaration,
    ) {}
}
