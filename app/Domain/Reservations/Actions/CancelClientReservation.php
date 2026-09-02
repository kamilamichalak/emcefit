<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\MakeupCredit;
use App\Domain\Reservations\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CancelClientReservation
{
    /** Minimalne wyprzedzenie (minuty) dające prawo do odrobienia — regulamin pkt 15-16. */
    public const GRACE_MINUTES = 60;

    /**
     * Zwalnia miejsce potwierdzonej rezerwacji klienta (regulamin pkt 16) — status
     * przechodzi na `zwolnione`. makeup_credit przysługuje tylko, gdy do rozpoczęcia
     * zajęć zostało co najmniej GRACE_MINUTES. Promowanie z listy oczekujących jest
     * osobnym krokiem (Prompt 14a) — tu go nie ruszamy.
     *
     * @return bool czy przyznano prawo do odrobienia
     */
    public function handle(Reservation $reservation): bool
    {
        return DB::transaction(function () use ($reservation): bool {
            $grantsCredit = $this->grantsMakeupCredit($reservation);

            $reservation->update(['status' => ReservationStatus::Released]);

            if ($grantsCredit) {
                MakeupCredit::create([
                    'client_id' => $reservation->client_id,
                    'source_reservation_id' => $reservation->id,
                    'expires_end_of_month' => true,
                    'used' => false,
                ]);
            }

            return $grantsCredit;
        });
    }

    /**
     * Czy zwolnienie miejsca w tej chwili daje jeszcze prawo do odrobienia.
     */
    public function grantsMakeupCredit(Reservation $reservation): bool
    {
        return CarbonImmutable::now()->lessThanOrEqualTo(
            $reservation->startsAt()->subMinutes(self::GRACE_MINUTES),
        );
    }
}
