<?php

namespace Database\Factories;

use App\Domain\Scheduling\Models\ClassType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassType>
 */
class ClassTypeFactory extends Factory
{
    protected $model = ClassType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'required_equipment' => fake()->boolean(40) ? fake()->randomElement(['sztangi', 'hantle', 'step', 'guma oporowa']) : null,
            'color' => strtoupper(fake()->hexColor()),
            'default_capacity' => 20,
        ];
    }
}
