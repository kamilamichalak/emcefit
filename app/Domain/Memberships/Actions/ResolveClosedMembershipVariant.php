<?php

namespace App\Domain\Memberships\Actions;

use App\Domain\Memberships\Data\ResolvedVariant;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Models\ClassSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Dobiera wariant karnetu zamkniętego dla zgłoszenia klienta na miesiąc (Prompt 10e):
 *
 * - liczy "tygodnie z obecnością" (tygodnie ISO, w których klient ma ≥1 "będę"),
 * - tygodnie, w których WSZYSTKIE wystąpienia zostały odwołane przez klub, są
 *   całkowicie pomijane (nie liczą się do żadnej strony),
 * - jeśli obecność jest w każdym tygodniu zajęć → wariant miesięczny (bez zmian),
 * - jeśli klient pomija całe tygodnie → krótszy pakiet "N tygodni od pierwszego
 *   wejścia", o ile taki jest w cenniku; w przeciwnym razie fallback do miesięcznego.
 */
final class ResolveClosedMembershipVariant
{
    /**
     * @param  list<int>  $classGroupIds
     * @param  list<int>  $absentScheduleIds  wystąpienia (class_schedule.id) oznaczone jako "nie będę"
     */
    public function handle(array $classGroupIds, CarbonImmutable $month, array $absentScheduleIds): ResolvedVariant
    {
        $sessionsPerWeek = count($classGroupIds);
        $monthly = MembershipType::monthlyClosedForSessions($sessionsPerWeek);

        $occurrences = ClassSchedule::query()
            ->whereIn('class_group_id', $classGroupIds)
            ->whereBetween('date', [
                $month->startOfMonth()->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->get(['id', 'date', 'status']);

        $absent = array_flip($absentScheduleIds);
        $today = CarbonImmutable::today();

        /** @var array<string, array{live: bool, attend: bool}> $weeks */
        $weeks = [];
        /** @var list<CarbonImmutable> $attendingDates */
        $attendingDates = [];

        foreach ($occurrences as $occurrence) {
            $date = CarbonImmutable::parse($occurrence->date);

            // Prompt 10h: minione wystąpienia nie liczą się do ceny ani do tygodni z obecnością
            if ($date->lt($today)) {
                continue;
            }

            $key = $date->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
            $clubCancelled = $occurrence->status === ClassOccurrenceStatus::Cancelled;
            $attending = ! $clubCancelled && ! isset($absent[$occurrence->id]);

            $weeks[$key] ??= ['live' => false, 'attend' => false];

            if (! $clubCancelled) {
                $weeks[$key]['live'] = true;
            }

            if ($attending) {
                $weeks[$key]['attend'] = true;
                $attendingDates[] = $date;
            }
        }

        $totalWeeks = count(array_filter($weeks, fn (array $week): bool => $week['live']));
        $attendanceWeeks = count(array_filter($weeks, fn (array $week): bool => $week['attend']));

        // Pełen miesiąc obecności (albo brak jakiejkolwiek obecności — nie ma czego skracać)
        // => wariant miesięczny, daty domyślne.
        if ($attendanceWeeks === 0 || $attendanceWeeks >= $totalWeeks) {
            return new ResolvedVariant(
                type: $monthly,
                firstEntryDate: null,
                endDate: null,
                attendanceWeeks: $attendanceWeeks,
                totalWeeks: $totalWeeks,
                shortened: false,
                fellBackToMonthly: false,
            );
        }

        $shorter = MembershipType::closedForSessionsAndWeeks($sessionsPerWeek, $attendanceWeeks);

        if ($shorter === null) {
            return new ResolvedVariant(
                type: $monthly,
                firstEntryDate: null,
                endDate: null,
                attendanceWeeks: $attendanceWeeks,
                totalWeeks: $totalWeeks,
                shortened: false,
                fellBackToMonthly: true,
            );
        }

        $dates = (new Collection($attendingDates))->sort()->values();

        return new ResolvedVariant(
            type: $shorter,
            firstEntryDate: $dates->first(),
            endDate: $dates->last(),
            attendanceWeeks: $attendanceWeeks,
            totalWeeks: $totalWeeks,
            shortened: true,
            fellBackToMonthly: false,
        );
    }
}
