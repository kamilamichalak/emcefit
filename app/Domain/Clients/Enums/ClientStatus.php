<?php

namespace App\Domain\Clients\Enums;

enum ClientStatus: string
{
    case Active = 'aktywny';
    case Inactive = 'nieaktywny';
}
