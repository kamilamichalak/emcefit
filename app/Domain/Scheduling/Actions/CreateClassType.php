<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Scheduling\Data\ClassTypeData;
use App\Domain\Scheduling\Models\ClassType;

final class CreateClassType
{
    public function handle(ClassTypeData $data): ClassType
    {
        return ClassType::create($data->toAttributes());
    }
}
