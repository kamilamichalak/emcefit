<?php

namespace Database\Factories;

use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassGroup>
 */
class ClassGroupFactory extends Factory
{
    protected $model = ClassGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_type_id' => ClassType::factory(),
            'trainer_id' => null,
            'weekday' => fake()->numberBetween(1, 5),
            'start_time' => fake()->randomElement(['08:00', '09:00', '17:00', '18:00', '19:00']),
            'duration_minutes' => 55,
            'capacity' => 20,
            'active_from' => now()->startOfMonth()->toDateString(),
            'active_to' => null,
        ];
    }

    public function forMonth(CarbonInterface $month): static
    {
        return $this->state(fn (): array => [
            'active_from' => $month->copy()->startOfMonth()->toDateString(),
        ]);
    }
}
