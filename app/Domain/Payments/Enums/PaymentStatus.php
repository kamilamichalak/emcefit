<?php

namespace App\Domain\Payments\Enums;

/**
 * Status platnosci — sekcja 4 spec.
 */
enum PaymentStatus: string
{
    case Oczekuje = 'oczekuje';
    case Zaksiegowana = 'zaksiegowana';
    case Anulowana = 'anulowana';
}
