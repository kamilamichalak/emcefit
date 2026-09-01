<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Scheduling\Data\ClassTypeData;
use App\Domain\Scheduling\Models\ClassType;

final class UpdateClassType
{
    public function handle(ClassType $classType, ClassTypeData $data): ClassType
    {
        $classType->update($data->toAttributes());

        return $classType;
    }
}
