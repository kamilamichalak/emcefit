<?php

namespace App\Domain\Reservations\Enums;

/**
 * Status rezerwacji na pojedyncze wystąpienie zajęć — spec sekcja 4.
 *
 * Uwaga: `Released` ("zwolnione") to rezygnacja klienta z pojedynczego miejsca
 * (regulamin pkt 16) — to co innego niż odwołanie CAŁYCH zajęć przez klub
 * (ClassOccurrenceStatus::Cancelled na class_schedule).
 */
enum ReservationStatus: string
{
    case PendingPayment = 'oczekuje_platnosci';
    case Confirmed = 'potwierdzona';
    case Waitlist = 'waitlist';
    case Released = 'zwolnione';
    case MadeUp = 'odrobiona';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Oczekuje na płatność',
            self::Confirmed => 'Potwierdzona',
            self::Waitlist => 'Lista oczekujących',
            self::Released => 'Zwolnione',
            self::MadeUp => 'Odrobiona',
        };
    }
}
