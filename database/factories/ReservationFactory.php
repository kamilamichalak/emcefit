<?php

namespace Database\Factories;

use App\Domain\Memberships\Models\Membership;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Models\ClassSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'membership_id' => Membership::factory(),
            'client_id' => fn (array $attributes) => Membership::find($attributes['membership_id'])->client_id,
            'class_schedule_id' => ClassSchedule::factory(),
            'status' => ReservationStatus::PendingPayment,
            'reported_at' => now(),
            'confirmed_at' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => ReservationStatus::Cancelled]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }
}
