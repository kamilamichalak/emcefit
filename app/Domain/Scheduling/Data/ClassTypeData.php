<?php

namespace App\Domain\Scheduling\Data;

/**
 * Dane typu zajec (slownik) przekazywane z warstwy HTTP do akcji domenowych.
 */
final readonly class ClassTypeData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $requiredEquipment,
        public string $color,
        public string $icon,
        public int $defaultCapacity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'required_equipment' => $this->requiredEquipment,
            'color' => $this->color,
            'icon' => $this->icon,
            'default_capacity' => $this->defaultCapacity,
        ];
    }
}
