<?php

namespace App\Domain\Scheduling\Data;

/**
 * Pojedyncze zajecia we wzorcu tygodniowym (class_groups). Bez active_from/active_to —
 * miesiac obowiazywania jest ustalany osobno (przy dodawaniu), a edycja go nie rusza.
 */
final readonly class ClassGroupData
{
    public function __construct(
        public int $classTypeId,
        public ?int $trainerId,
        public int $weekday,
        public string $startTime,
        public int $durationMinutes,
        public int $capacity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'class_type_id' => $this->classTypeId,
            'trainer_id' => $this->trainerId,
            'weekday' => $this->weekday,
            'start_time' => $this->startTime,
            'duration_minutes' => $this->durationMinutes,
            'capacity' => $this->capacity,
        ];
    }
}
