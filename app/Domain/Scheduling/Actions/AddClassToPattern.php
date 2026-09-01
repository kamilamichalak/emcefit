<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Scheduling\Data\ClassGroupData;
use App\Domain\Scheduling\Models\ClassGroup;
use Carbon\CarbonImmutable;

final class AddClassToPattern
{
    /**
     * Dodaje zajecia do wzorca obowiazujacego od wskazanego miesiaca (bezterminowo,
     * active_to = null). Logika "nowy miesiac kopiuje poprzedni" to osobny krok.
     */
    public function handle(CarbonImmutable $month, ClassGroupData $data): ClassGroup
    {
        return ClassGroup::create([
            ...$data->toAttributes(),
            'active_from' => $month->startOfMonth()->toDateString(),
            'active_to' => null,
        ]);
    }
}
