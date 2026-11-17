<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\Enums\ReservationKind;
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

            $early = $this->isEarlyEnough($reservation);

            $reservation->update([
                'status' => ReservationStatus::Released,
                // za późno: wejście z karnetu otwartego / odrobienie przepada
                'late_release' => ! $early,
            ]);

            // zajęcia odrabiane zwolnione ≥1 h przed → odrobienie wraca do wykorzystania (do końca miesiąca)
            if ($reservation->kind === ReservationKind::Makeup && $early && $reservation->makeup_credit_id) {
                MakeupCredit::whereKey($reservation->makeup_credit_id)->update(['used' => false]);
                $reservation->update(['makeup_credit_id' => null]);
            }

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
        // odrobienie i wejście z karnetu otwartego nie dają kolejnego odrobienia
        return $reservation->kind === ReservationKind::Fixed && $this->isEarlyEnough($reservation);
    }

    /**
     * Czy klientka zwalnia miejsce co najmniej GRACE_MINUTES przed zajęciami — wtedy
     * przy karnecie otwartym wejście wraca do puli.
     */
    public function isEarlyEnough(Reservation $reservation): bool
    {
        return CarbonImmutable::now()->lessThanOrEqualTo(
            $reservation->startsAt()->subMinutes(self::GRACE_MINUTES),
        );
    }

    /**
     * Czy zwolnienie w tej chwili oznacza dla klientki stratę (odrobienia, wejścia) —
     * wtedy musi to świadomie potwierdzić.
     */
    public function causesLoss(Reservation $reservation): bool
    {
        return match ($reservation->kind) {
            ReservationKind::Fixed => ! $this->isEarlyEnough($reservation),
            ReservationKind::Makeup => ! $this->isEarlyEnough($reservation),
            ReservationKind::OpenEntry => ! $this->isEarlyEnough($reservation),
        };
    }
}
