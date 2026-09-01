<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Scheduling\Data\ClassGroupData;
use App\Domain\Scheduling\Models\ClassGroup;

final class UpdateClassGroup
{
    /**
     * Edycja w miejscu — active_from / active_to pozostaja bez zmian.
     */
    public function handle(ClassGroup $classGroup, ClassGroupData $data): ClassGroup
    {
        $classGroup->update($data->toAttributes());

        return $classGroup;
    }
}
