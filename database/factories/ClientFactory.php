<?php

namespace Database\Factories;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'phone' => fake()->numerify('### ### ###'),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-16 years')->format('Y-m-d'),
            'status' => ClientStatus::Active,
            'join_date' => now()->toDateString(),
            'terms_accepted_at' => now(),
            'health_declaration_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => ClientStatus::Inactive]);
    }
}
