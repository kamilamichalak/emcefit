<?php

namespace App\Domain\Reservations\Enums;

/**
 * Status rezerwacji na pojedyncze wystąpienie zajęć — spec sekcja 4.
 */
enum ReservationStatus: string
{
    case PendingPayment = 'oczekuje_platnosci';
    case Confirmed = 'potwierdzona';
    case Waitlist = 'waitlist';
    case Cancelled = 'odwolana';
    case MadeUp = 'odrobiona';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Oczekuje na płatność',
            self::Confirmed => 'Potwierdzona',
            self::Waitlist => 'Lista oczekujących',
            self::Cancelled => 'Odwołana',
            self::MadeUp => 'Odrobiona',
        };
    }
}
