<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ConfirmReservationsForPayment
{
    /**
     * Po zaksięgowaniu wpłaty przechodzi po rezerwacjach tego karnetu w stanie
     * "oczekuje_platnosci" (w kolejności dat zajęć) i — zależnie od limitu miejsc
     * danego wystąpienia — ustawia je jako "potwierdzona" albo "waitlist". Data
     * potwierdzenia = data zaksięgowania wpłaty (decyduje o kolejności na liście
     * oczekujących, regulamin pkt 36). Spec sekcja 16.
     *
     * @return array{confirmed: list<string>, waitlisted: list<string>}
     */
    public function handle(Payment $payment): array
    {
        if ($payment->status !== PaymentStatus::Settled) {
            return ['confirmed' => [], 'waitlisted' => []];
        }

        $confirmedAt = CarbonImmutable::parse($payment->settled_date)->startOfDay();

        $pending = Reservation::query()
            ->where('membership_id', $payment->membership_id)
            ->where('status', ReservationStatus::PendingPayment)
            ->with('classSchedule.classGroup.classType:id,name')
            ->get()
            ->sortBy(fn (Reservation $reservation): string => $reservation->classSchedule->date->toDateString()
                .$reservation->classSchedule->start_time)
            ->values();

        $confirmed = [];
        $waitlisted = [];

        DB::transaction(function () use ($pending, $confirmedAt, &$confirmed, &$waitlisted): void {
            foreach ($pending as $reservation) {
                $schedule = $reservation->classSchedule;

                $taken = Reservation::query()
                    ->where('class_schedule_id', $schedule->id)
                    ->where('status', ReservationStatus::Confirmed)
                    ->count();

                $hasRoom = $taken < $schedule->classGroup->capacity;

                $reservation->update([
                    'status' => $hasRoom ? ReservationStatus::Confirmed : ReservationStatus::Waitlist,
                    'confirmed_at' => $confirmedAt,
                ]);

                $label = $schedule->classGroup->classType->name.', '.$schedule->date->translatedFormat('j F');

                if ($hasRoom) {
                    $confirmed[] = $label;
                } else {
                    $waitlisted[] = $label;
                }
            }
        });

        return ['confirmed' => $confirmed, 'waitlisted' => $waitlisted];
    }
}
