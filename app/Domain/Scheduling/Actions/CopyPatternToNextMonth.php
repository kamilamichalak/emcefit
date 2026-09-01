<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Scheduling\Data\PatternCopyResult;
use App\Domain\Scheduling\Models\ClassGroup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CopyPatternToNextMonth
{
    /**
     * Kopiuje wzorzec obowiazujacy w `month` na kolejny miesiac:
     * - zamyka biezace, wciaz otwarte wiersze (obowiazuje_do = biezacy miesiac)
     * - tworzy kopie z obowiazuje_od = kolejny miesiac, obowiazuje_do = null
     *
     * Gdy wzorzec przypisany juz do kolejnego (lub pozniejszego) miesiaca istnieje
     * i brak `force` — nic nie zmienia i zwraca status 'conflict'.
     */
    public function handle(CarbonImmutable $month, bool $force = false): PatternCopyResult
    {
        $sourceMonth = $month->startOfMonth();
        $nextMonth = $sourceMonth->addMonthNoOverflow();
        $nextMonthKey = $nextMonth->format('Y-m');

        $sourceGroups = ClassGroup::query()->activeForMonth($sourceMonth)->get();

        if ($sourceGroups->isEmpty()) {
            return PatternCopyResult::emptySource($nextMonthKey);
        }

        $conflicting = ClassGroup::query()
            ->whereDate('active_from', '>=', $nextMonth->toDateString())
            ->count();

        if ($conflicting > 0 && ! $force) {
            return PatternCopyResult::conflict($conflicting, $nextMonthKey);
        }

        return DB::transaction(function () use ($sourceGroups, $sourceMonth, $nextMonth, $nextMonthKey, $force): PatternCopyResult {
            if ($force) {
                ClassGroup::query()
                    ->whereDate('active_from', '>=', $nextMonth->toDateString())
                    ->delete();
            }

            foreach ($sourceGroups as $group) {
                if ($group->active_to === null) {
                    $group->update(['active_to' => $sourceMonth->toDateString()]);
                }

                ClassGroup::create([
                    'class_type_id' => $group->class_type_id,
                    'trainer_id' => $group->trainer_id,
                    'weekday' => $group->weekday,
                    'start_time' => $group->start_time,
                    'duration_minutes' => $group->duration_minutes,
                    'capacity' => $group->capacity,
                    'active_from' => $nextMonth->toDateString(),
                    'active_to' => null,
                ]);
            }

            return PatternCopyResult::copied($sourceGroups->count(), $nextMonthKey);
        });
    }
}
