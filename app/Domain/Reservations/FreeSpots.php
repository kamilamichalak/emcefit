<?php

namespace App\Domain\Reservations;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Enums\MembershipMode;
use App\Domain\Memberships\Enums\ValidityPeriodType;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Reservations\Enums\ReservationKind;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\MakeupCredit;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Models\ClassSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Zajmowanie wolnych miejsc: co klientce przysługuje (odrobienia, wejścia z karnetu
 * otwartego) i na jakie zajęcia może się jeszcze zapisać.
 *
 * Zasady (ustalone z Kamilą 17.11.2026):
 * - odrobienie: dowolne zajęcia z wolnym miejscem w bieżącym miesiącu, do startu zajęć;
 * - karnet otwarty: to samo, w okresie ważności karnetu, do wyczerpania wejść, także przed wpłatą;
 * - „wolne miejsce” = pojemność minus potwierdzeni minus czekający na płatność
 *   (ci drudzy mają pierwszeństwo — zapisali się na cały miesiąc).
 */
final class FreeSpots
{
    /** Statusy, które zajmują miejsce albo blokują ponowny zapis tej samej osoby. */
    private const ACTIVE = [ReservationStatus::Confirmed, ReservationStatus::PendingPayment, ReservationStatus::Waitlist];

    /** Najstarsze niewykorzystane odrobienie ważne w bieżącym miesiącu. */
    public function nextMakeupCredit(Client $client): ?MakeupCredit
    {
        return $client->makeupCredits()->available()->orderBy('created_at')->orderBy('id')->first();
    }

    public function makeupCreditsCount(Client $client): int
    {
        return $client->makeupCredits()->available()->count();
    }

    /**
     * Karnet otwarty, z którego klientka może teraz zająć miejsce, z pozostałymi wejściami
     * i okresem, w którym da się z niego korzystać.
     *
     * @return array{membership: Membership, remaining: int, from: CarbonImmutable, to: CarbonImmutable}|null
     */
    public function openEntitlement(Client $client): ?array
    {
        $today = CarbonImmutable::today();

        $memberships = $client->memberships()
            ->whereHas('membershipType', fn ($q) => $q->where('mode', MembershipMode::Open))
            ->with(['membershipType', 'reservations.classSchedule'])
            ->orderBy('start_date')
            ->get();

        foreach ($memberships as $membership) {
            $used = $membership->reservations->filter(fn (Reservation $r) => $this->countsAsEntry($r));
            $remaining = (int) $membership->membershipType->entry_count - $used->count();

            if ($remaining <= 0) {
                continue;
            }

            [$from, $to] = $this->openWindow($membership, $used);

            if ($to->lt($today)) {
                continue;
            }

            return ['membership' => $membership, 'remaining' => $remaining, 'from' => $from, 'to' => $to];
        }

        return null;
    }

    /**
     * Wejście zużyte: aktywne miejsce albo zwolnione za późno (mniej niż 1 h przed).
     */
    private function countsAsEntry(Reservation $reservation): bool
    {
        return $reservation->kind === ReservationKind::OpenEntry
            && (in_array($reservation->status, self::ACTIVE, true)
                || $reservation->status === ReservationStatus::MadeUp
                || ($reservation->status === ReservationStatus::Released && $reservation->late_release));
    }

    /**
     * Okres ważności karnetu otwartego. „X tygodni od pierwszego wejścia”: liczone od daty
     * pierwszych zajęć, na które się zapisała; dopóki nie ma żadnych — od dziś.
     *
     * @param  Collection<int, Reservation>  $used
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function openWindow(Membership $membership, Collection $used): array
    {
        $type = $membership->membershipType;
        $start = CarbonImmutable::parse($membership->start_date)->startOfDay();
        $from = $start->max(CarbonImmutable::today());

        if ($membership->end_date) {
            return [$from, CarbonImmutable::parse($membership->end_date)->endOfDay()];
        }

        if ($type->validity_period_type === ValidityPeriodType::WeeksFromFirstEntry) {
            $first = $used->map(fn (Reservation $r) => CarbonImmutable::parse($r->classSchedule->date))->min();
            $base = $first ?? $from;

            return [$from, $base->addWeeks((int) $type->validity_period_value)->subDay()->endOfDay()];
        }

        // miesiąc kalendarzowy (domyślnie)
        return [$from, $start->endOfMonth()];
    }

    /**
     * Zajęcia, na które klientka może teraz zająć miejsce, z informacją, z czego skorzysta.
     *
     * @return Collection<int, array{occurrence: ClassSchedule, free: int, kind: ReservationKind}>
     */
    public function available(Client $client): Collection
    {
        $now = CarbonImmutable::now();
        $credits = $this->makeupCreditsCount($client);
        $open = $this->openEntitlement($client);

        if ($credits === 0 && $open === null) {
            return collect();
        }

        $from = CarbonImmutable::today();
        $to = collect([
            $credits > 0 ? $now->endOfMonth() : null,
            $open['to'] ?? null,
        ])->filter()->max();

        $occurrences = ClassSchedule::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', ClassOccurrenceStatus::Cancelled)
            ->with(['classGroup.classType', 'reservations'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return $occurrences
            ->map(function (ClassSchedule $occurrence) use ($client, $credits, $open, $now): ?array {
                $startsAt = CarbonImmutable::parse($occurrence->date->toDateString())->setTimeFromTimeString($occurrence->start_time);

                if (! $startsAt->isAfter($now)) {
                    return null;
                }

                if ($occurrence->reservations->contains(fn (Reservation $r) => $r->client_id === $client->id && in_array($r->status, self::ACTIVE, true))) {
                    return null;
                }

                $free = $this->freeSeats($occurrence);
                $kind = $this->kindFor($startsAt, $credits, $open);

                if ($free <= 0 || $kind === null) {
                    return null;
                }

                return ['occurrence' => $occurrence, 'free' => $free, 'kind' => $kind];
            })
            ->filter()
            ->values();
    }

    /**
     * Z czego skorzysta zapis na zajęcia w danym terminie: najpierw odrobienie (wygasa
     * z końcem miesiąca), potem wejście z karnetu otwartego.
     *
     * @param  array{membership: Membership, remaining: int, from: CarbonImmutable, to: CarbonImmutable}|null  $open
     */
    public function kindFor(CarbonImmutable $startsAt, int $credits, ?array $open): ?ReservationKind
    {
        if ($credits > 0 && $startsAt->isSameMonth(CarbonImmutable::now())) {
            return ReservationKind::Makeup;
        }

        if ($open !== null && $startsAt->betweenIncluded($open['from'], $open['to'])) {
            return ReservationKind::OpenEntry;
        }

        return null;
    }

    public function freeSeats(ClassSchedule $occurrence): int
    {
        $taken = $occurrence->reservations
            ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::PendingPayment])
            ->count();

        return max(0, $occurrence->classGroup->capacity - $taken);
    }
}
