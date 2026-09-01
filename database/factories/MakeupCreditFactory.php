<?php

namespace Database\Factories;

use App\Domain\Reservations\Models\MakeupCredit;
use App\Domain\Reservations\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MakeupCredit>
 */
class MakeupCreditFactory extends Factory
{
    protected $model = MakeupCredit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_reservation_id' => Reservation::factory()->cancelled(),
            'client_id' => fn (array $attributes) => Reservation::find($attributes['source_reservation_id'])->client_id,
            'expires_end_of_month' => true,
            'used' => false,
        ];
    }

    public function used(): static
    {
        return $this->state(fn (): array => ['used' => true]);
    }
}
