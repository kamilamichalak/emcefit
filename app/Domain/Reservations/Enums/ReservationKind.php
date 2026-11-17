<?php

namespace App\Domain\Reservations\Enums;

/**
 * Skąd wzięło się miejsce na zajęciach.
 */
enum ReservationKind: string
{
    /** Stałe miejsce z karnetu zamkniętego (zapis na cały miesiąc). */
    case Fixed = 'stala';

    /** Wolne miejsce zajęte w ramach odrobienia (makeup_credit). */
    case Makeup = 'odrobienie';

    /** Wolne miejsce zajęte w ramach wejścia z karnetu otwartego. */
    case OpenEntry = 'otwarty';

    /** Dopisek na liście zajęć dla studia (pusty dla stałych miejsc). */
    public function label(): string
    {
        return match ($this) {
            self::Fixed => '',
            self::Makeup => 'odrabia',
            self::OpenEntry => 'karnet otwarty',
        };
    }
}
