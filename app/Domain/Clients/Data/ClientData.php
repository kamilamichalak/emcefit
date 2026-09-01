<?php

namespace App\Domain\Clients\Data;

/**
 * Dane podstawowe klienta z formularza admina. Daty jako 'Y-m-d' albo null.
 * Hasło, zgody i status klient/aktywacja ustawiają osobno (Prompt 9).
 */
final readonly class ClientData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $birthDate,
    ) {}
}
