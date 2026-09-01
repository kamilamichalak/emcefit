<?php

namespace Database\Factories;

use App\Domain\Trainers\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trainer>
 */
class TrainerFactory extends Factory
{
    protected $model = Trainer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'specialization' => fake()->randomElement([
                'Trening siłowy', 'Zajęcia taneczne', 'Trening funkcjonalny', null,
            ]),
        ];
    }
}
