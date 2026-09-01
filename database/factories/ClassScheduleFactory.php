<?php

namespace Database\Factories;

use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSchedule>
 */
class ClassScheduleFactory extends Factory
{
    protected $model = ClassSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_group_id' => ClassGroup::factory(),
            'date' => now()->toDateString(),
            'start_time' => '18:00',
            'status' => ClassOccurrenceStatus::Planned,
            'cancellation_reason' => null,
        ];
    }

    public function cancelled(string $reason = 'Święto'): static
    {
        return $this->state(fn (): array => [
            'status' => ClassOccurrenceStatus::Cancelled,
            'cancellation_reason' => $reason,
        ]);
    }
}
