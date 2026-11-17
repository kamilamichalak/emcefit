<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Reservations\Enums\ReservationKind;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\FreeSpots;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Models\ClassSchedule;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Klientka zajmuje wolne miejsce — w ramach odrobienia albo wejścia z karnetu otwartego.
 */
final class BookFreeSpot
{
    public function __construct(private FreeSpots $freeSpots) {}

    /**
     * @throws DomainException z komunikatem dla klientki, gdy zapis jest niemożliwy
     */
    public function handle(Client $client, ClassSchedule $occurrence): Reservation
    {
        return DB::transaction(function () use ($client, $occurrence): Reservation {
            // blokada wiersza zajęć — dwie osoby nie zajmą ostatniego miejsca naraz
            $occurrence = ClassSchedule::query()->lockForUpdate()->with('classGroup')->findOrFail($occurrence->id);
            $occurrence->load('reservations');

            $startsAt = CarbonImmutable::parse($occurrence->date->toDateString())->setTimeFromTimeString($occurrence->start_time);

            if ($occurrence->status === ClassOccurrenceStatus::Cancelled) {
                throw new DomainException('Te zajęcia zostały odwołane.');
            }

            if (! $startsAt->isFuture()) {
                throw new DomainException('Te zajęcia już się zaczęły.');
            }

            $existing = $occurrence->reservations->firstWhere('client_id', $client->id);

            if ($existing && $existing->status !== ReservationStatus::Released) {
                throw new DomainException('Jesteś już zapisana na te zajęcia.');
            }

            if ($this->freeSpots->freeSeats($occurrence) <= 0) {
                throw new DomainException('Brak wolnych miejsc — ktoś był szybszy. Wybierz inne zajęcia.');
            }

            $credit = $this->freeSpots->nextMakeupCredit($client);
            $open = $this->freeSpots->openEntitlement($client);
            $kind = $this->freeSpots->kindFor($startsAt, $credit ? 1 : 0, $open);

            if ($kind === null) {
                throw new DomainException('Nie masz już zajęć do odrobienia ani wejść do wykorzystania na ten termin.');
            }

            $attributes = [
                'client_id' => $client->id,
                'class_schedule_id' => $occurrence->id,
                'status' => ReservationStatus::Confirmed,
                'kind' => $kind,
                'late_release' => false,
                'reported_at' => now(),
                'confirmed_at' => now(),
                'membership_id' => $kind === ReservationKind::Makeup
                    ? $credit->sourceReservation->membership_id
                    : $open['membership']->id,
                'makeup_credit_id' => $kind === ReservationKind::Makeup ? $credit->id : null,
            ];

            // unikalność (klientka, zajęcia): wcześniej zwolnione miejsce na tym samym terminie — używamy wiersza ponownie
            $reservation = $existing
                ? tap($existing)->update($attributes)
                : Reservation::create($attributes);

            if ($kind === ReservationKind::Makeup) {
                $credit->update(['used' => true]);
            }

            return $reservation;
        });
    }
}
