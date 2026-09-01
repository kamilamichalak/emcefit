<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Scheduling\Data\PatternCopyResult;
use App\Domain\Scheduling\Models\ClassGroup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CopyPatternIntoMonth
{
    /**
     * Tworzy wlasny wzorzec dla `targetMonth` jako kopie wzorca dziedziczonego
     * (obowiazujacego dla tego miesiaca, ale zakotwiczonego we wczesniejszym).
     *
     * - dziedziczone wiersze zostaja zamkniete (active_to = miesiac przed docelowym)
     * - powstaja ich kopie z active_from = targetMonth, active_to = null
     * - gdy targetMonth ma juz WLASNY wzorzec i brak `force` -> status 'conflict'
     * - gdy nie ma czego kopiowac -> status 'empty'
     */
    public function handle(CarbonImmutable $targetMonth, bool $force = false): PatternCopyResult
    {
        $target = $targetMonth->startOfMonth();
        $targetKey = $target->format('Y-m');
        $beforeTarget = $target->subMonthNoOverflow();

        $effective = ClassGroup::query()->activeForMonth($target)->get();

        $ownForTarget = $effective->filter(
            fn (ClassGroup $group): bool => $group->active_from->format('Y-m') === $targetKey,
        );
        $inherited = $effective->reject(
            fn (ClassGroup $group): bool => $group->active_from->format('Y-m') === $targetKey,
        );

        if ($ownForTarget->isNotEmpty() && ! $force) {
            return PatternCopyResult::conflict($ownForTarget->count(), $targetKey);
        }

        if ($inherited->isEmpty()) {
            return PatternCopyResult::emptySource($targetKey);
        }

        return DB::transaction(function () use ($inherited, $ownForTarget, $target, $beforeTarget, $targetKey, $force): PatternCopyResult {
            if ($force) {
                ClassGroup::query()->whereIn('id', $ownForTarget->pluck('id'))->delete();
            }

            foreach ($inherited as $group) {
                $group->update(['active_to' => $beforeTarget->toDateString()]);

                ClassGroup::create([
                    'class_type_id' => $group->class_type_id,
                    'trainer_id' => $group->trainer_id,
                    'weekday' => $group->weekday,
                    'start_time' => $group->start_time,
                    'duration_minutes' => $group->duration_minutes,
                    'capacity' => $group->capacity,
                    'active_from' => $target->toDateString(),
                    'active_to' => null,
                ]);
            }

            return PatternCopyResult::copied($inherited->count(), $targetKey);
        });
    }
}
