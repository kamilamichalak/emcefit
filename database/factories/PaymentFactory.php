<?php

namespace Database\Factories;

use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $membership = Membership::factory();

        return [
            'membership_id' => $membership,
            'client_id' => fn (array $attributes) => Membership::find($attributes['membership_id'])->client_id,
            'amount' => 160.00,
            'reported_date' => now()->toDateString(),
            'settled_date' => null,
            'status' => PaymentStatus::Pending,
            'transfer_title' => 'Przelew — karnet',
        ];
    }

    public function settled(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Settled,
            'settled_date' => now()->toDateString(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Cancelled,
            'settled_date' => null,
        ]);
    }
}
