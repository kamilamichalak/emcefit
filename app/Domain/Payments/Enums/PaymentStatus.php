<?php

namespace App\Domain\Payments\Enums;

/**
 * Payment status — spec section 4.
 */
enum PaymentStatus: string
{
    case Pending = 'oczekuje';
    case Settled = 'zaksiegowana';
    case Cancelled = 'anulowana';
}
