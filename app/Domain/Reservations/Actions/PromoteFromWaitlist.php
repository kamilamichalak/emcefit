<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Models\ClassSchedule;
use Illuminate\Support\Facades\DB;

/**
 * Promuje jedną osobę z listy oczekujących na dane wystąpienie zajęć, gdy zwolniło
 * się miejsce (np. po odwołaniu rezerwacji przez klienta — Prompt 14, albo po
 * cofnięciu zaksięgowania płatności przez admina). Kolejność: najwcześniejsza
 * `confirmed_at` = kto z listy oczekujących pierwszy faktycznie zapłacił
 * (regulamin pkt 36).
 *
 * W MVP bez powiadomienia — promowana osoba zobaczy nowy status przy następnym
 * wejściu na „Moje zajęcia".
 */
final class PromoteFromWaitlist
{
    public function handle(ClassSchedule $occurrence): ?Reservation
    {
        return DB::transaction(function () use ($occurrence): ?Reservation {
            $confirmed = Reservation::query()
                ->where('class_schedule_id', $occurrence->id)
                ->where('status', ReservationStatus::Confirmed)
                ->lockForUpdate()
                ->count();

            if ($confirmed >= $occurrence->classGroup->capacity) {
                return null;
            }

            $next = Reservation::query()
                ->where('class_schedule_id', $occurrence->id)
                ->where('status', ReservationStatus::Waitlist)
                ->orderBy('confirmed_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($next === null) {
                return null;
            }

            // `confirmed_at` zostaje bez zmian — ustawiono je przy zaksięgowaniu wpłaty
            // i to ono decyduje o kolejności.
            $next->update(['status' => ReservationStatus::Confirmed]);

            return $next;
        });
    }
}
