<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Models\ClassSchedule;

final class CancelClassOccurrence
{
    /**
     * Odwoluje pojedyncze wystapienie zajec. Nie rusza wzorca (class_groups) ani
     * innych wystapien — modyfikuje wylacznie ten jeden wiersz class_schedule.
     */
    public function cancel(ClassSchedule $occurrence, string $reason): ClassSchedule
    {
        $occurrence->update([
            'status' => ClassOccurrenceStatus::Cancelled,
            'cancellation_reason' => $reason,
        ]);

        return $occurrence;
    }

    public function restore(ClassSchedule $occurrence): ClassSchedule
    {
        $occurrence->update([
            'status' => ClassOccurrenceStatus::Planned,
            'cancellation_reason' => null,
        ]);

        return $occurrence;
    }
}
