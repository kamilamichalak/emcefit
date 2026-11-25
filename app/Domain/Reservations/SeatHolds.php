<?php

namespace App\Domain\Reservations;

use App\Domain\Memberships\Models\Membership;
use App\Domain\Scheduling\Models\ClassGroup;
use Carbon\CarbonImmutable;

/**
 * Miejsca „trzymane” dla stałych uczestniczek (Majka, 25.11.2026): dopóki nie zacznie się
 * miesiąc, dziewczyny, które chodziły na dane zajęcia w poprzednim miesiącu i jeszcze się
 * nie zapisały, mają swoje miejsce zarezerwowane — inne osoby go nie zajmą. Od 1. dnia
 * miesiąca rezerwacja wygasa sama (nikogo nie zapisujemy ani nie obciążamy).
 */
final class SeatHolds
{
    /** @var array<string, array{0: array<string, list<int>>, 1: array<string, list<int>>}> miesiąc → [bez zapisu, zapisane bez wpłaty] */
    private array $cache = [];

    /**
     * Ile miejsc w grupie (w danym miesiącu) trzymamy dla stałych uczestniczek.
     *
     * $includeUnpaid: czy liczyć też stałe, które już się zapisały, ale nie zapłaciły — potrzebne
     * tam, gdzie miejsce zajmuje dopiero wpłata (strona zapisów). Tam, gdzie zapis bez wpłaty
     * i tak liczy się jako zajęte miejsce („Wolne miejsca”), liczymy tylko te bez zapisu.
     */
    public function count(ClassGroup $group, CarbonImmutable $month, ?int $exceptClientId = null, bool $includeUnpaid = false): int
    {
        [$notEnrolled, $unpaid] = $this->holders($month);
        $key = $this->key($group);
        $clients = array_merge($notEnrolled[$key] ?? [], $includeUnpaid ? ($unpaid[$key] ?? []) : []);

        return count(array_filter(array_unique($clients), fn (int $id) => $id !== $exceptClientId));
    }

    /** @return array{0: array<string, list<int>>, 1: array<string, list<int>>} */
    private function holders(CarbonImmutable $month): array
    {
        $month = $month->startOfMonth();

        if (! CarbonImmutable::today()->lt($month)) {
            return [[], []];
        }

        $monthKey = $month->format('Y-m');

        if (isset($this->cache[$monthKey])) {
            return $this->cache[$monthKey];
        }

        $current = Membership::query()->whereDate('start_date', $month->toDateString())->with(['payments', 'classGroups'])->get()->keyBy('client_id');

        $notEnrolled = [];
        $unpaid = [];
        Membership::query()
            ->whereDate('start_date', $month->subMonthNoOverflow()->toDateString())
            ->with('classGroups')
            ->get()
            ->each(function (Membership $m) use (&$notEnrolled, &$unpaid, $current) {
                $next = $current->get($m->client_id);

                if ($next !== null && $next->isPaid()) {
                    return; // zapisana i opłacona — zajmuje miejsce normalnie
                }

                if ($next === null) {
                    // jeszcze bez zapisu — trzymamy miejsca na zajęciach z poprzedniego miesiąca
                    foreach ($m->classGroups as $group) {
                        $notEnrolled[$this->key($group)][] = $m->client_id;
                    }

                    return;
                }

                // zapisana, bez wpłaty — trzymamy miejsca na zajęciach, które wybrała teraz
                foreach ($next->classGroups as $group) {
                    $unpaid[$this->key($group)][] = $m->client_id;
                }
            });

        return $this->cache[$monthKey] = [$notEnrolled, $unpaid];
    }

    /** Te same zajęcia w kolejnym miesiącu = ten sam dzień, godzina i rodzaj (grupy są per miesiąc). */
    private function key(ClassGroup $group): string
    {
        return $group->weekday->value.'|'.substr((string) $group->start_time, 0, 5).'|'.$group->class_type_id;
    }
}
