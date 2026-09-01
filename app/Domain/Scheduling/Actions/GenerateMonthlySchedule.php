<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Scheduling\Data\ScheduleGenerationResult;
use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GenerateMonthlySchedule
{
    /**
     * Klonuje kazdy dzien tygodnia z aktualnego wzorca (class_groups obowiazujacych
     * w danym miesiacu) na wszystkie pasujace daty tego miesiaca.
     *
     * - bez `regenerate`: jesli sa juz jakiekolwiek wystapienia w tym miesiacu,
     *   nie robi nic i zwraca status 'exists'
     * - z `regenerate`: usuwa wystapienia PLANOWANE (odwolane zostawia nietkniete),
     *   po czym odbudowuje je z wzorca
     */
    public function handle(CarbonImmutable $month, bool $regenerate = false): ScheduleGenerationResult
    {
        $monthStart = $month->startOfMonth();
        $monthEnd = $month->endOfMonth();

        $existing = ClassSchedule::query()
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        if ($existing > 0 && ! $regenerate) {
            return ScheduleGenerationResult::alreadyExists($existing);
        }

        return DB::transaction(function () use ($monthStart, $monthEnd, $regenerate): ScheduleGenerationResult {
            $removed = 0;

            if ($regenerate) {
                $removed = ClassSchedule::query()
                    ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->where('status', ClassOccurrenceStatus::Planned)
                    ->delete();
            }

            $groups = ClassGroup::query()->activeForMonth($monthStart)->get();

            $created = 0;

            foreach ($groups as $group) {
                for ($date = $monthStart; $date->lessThanOrEqualTo($monthEnd); $date = $date->addDay()) {
                    if ($date->dayOfWeekIso !== $group->weekday->value) {
                        continue;
                    }

                    $occurrence = ClassSchedule::firstOrCreate(
                        ['class_group_id' => $group->id, 'date' => $date->toDateString()],
                        ['start_time' => $group->start_time, 'status' => ClassOccurrenceStatus::Planned],
                    );

                    if ($occurrence->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }

            return ScheduleGenerationResult::generated($created, $removed);
        });
    }
}
