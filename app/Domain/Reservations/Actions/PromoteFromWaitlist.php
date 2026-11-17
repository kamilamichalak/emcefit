<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\Enums\ReservationKind;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\FreeSpots;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Mail\WaitlistPromotedMail;
use App\Support\ClientMailer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Promuje jedną osobę z listy oczekujących na dane wystąpienie zajęć, gdy zwolniło
 * się miejsce. Kolejność: najwcześniejsza `confirmed_at` (kto pierwszy zapłacił albo
 * pierwszy dopisał się do listy — regulamin pkt 36).
 *
 * Lista oczekujących z „Wolnych miejsc” (odrobienie / karnet otwarty): odrobienie albo
 * wejście zużywa się dopiero przy wskoczeniu. Jeśli klientce już ich zabrakło, wypada
 * z listy i miejsce dostaje kolejna osoba. Promowana klientka dostaje maila.
 */
final class PromoteFromWaitlist
{
    public function __construct(private FreeSpots $freeSpots) {}

    public function handle(ClassSchedule $occurrence): ?Reservation
    {
        $promoted = DB::transaction(function () use ($occurrence): ?Reservation {
            $confirmed = Reservation::query()
                ->where('class_schedule_id', $occurrence->id)
                ->where('status', ReservationStatus::Confirmed)
                ->lockForUpdate()
                ->count();

            if ($confirmed >= $occurrence->classGroup->capacity) {
                return null;
            }

            $queue = Reservation::query()
                ->where('class_schedule_id', $occurrence->id)
                ->where('status', ReservationStatus::Waitlist)
                ->orderBy('confirmed_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->with('client')
                ->get();

            foreach ($queue as $next) {
                if ($this->promote($next, $occurrence)) {
                    return $next;
                }
            }

            return null;
        });

        if ($promoted !== null) {
            ClientMailer::send($promoted->client, new WaitlistPromotedMail($promoted));
        }

        return $promoted;
    }

    /**
     * @return bool czy klientka dostała miejsce (false = wypadła z listy)
     */
    private function promote(Reservation $next, ClassSchedule $occurrence): bool
    {
        // `confirmed_at` zostaje bez zmian — decyduje o kolejności.
        if ($next->kind === ReservationKind::Fixed) {
            $next->update(['status' => ReservationStatus::Confirmed]);

            return true;
        }

        $startsAt = CarbonImmutable::parse($occurrence->date->toDateString())->setTimeFromTimeString($occurrence->start_time);

        if ($next->kind === ReservationKind::Makeup) {
            $credit = $this->freeSpots->nextMakeupCredit($next->client);

            if ($credit === null || ! $startsAt->isSameMonth(CarbonImmutable::now())) {
                $next->update(['status' => ReservationStatus::Released]);

                return false;
            }

            $next->update([
                'status' => ReservationStatus::Confirmed,
                'makeup_credit_id' => $credit->id,
                'membership_id' => $credit->sourceReservation->membership_id,
            ]);
            $credit->update(['used' => true]);
            $this->freeSpots->dropStaleWaitlists($next->client);

            return true;
        }

        $open = $this->freeSpots->openEntitlement($next->client);

        if ($open === null || ! $startsAt->betweenIncluded($open['from'], $open['to'])) {
            $next->update(['status' => ReservationStatus::Released]);

            return false;
        }

        $next->update(['status' => ReservationStatus::Confirmed, 'membership_id' => $open['membership']->id]);
        $this->freeSpots->dropStaleWaitlists($next->client);

        return true;
    }
}
