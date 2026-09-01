<?php

namespace App\Domain\Clients\Enums;

enum ClientStatus: string
{
    case Aktywny = 'aktywny';
    case Nieaktywny = 'nieaktywny';
}
