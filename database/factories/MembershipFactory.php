<?php

namespace Database\Factories;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'membership_type_id' => MembershipType::factory(),
            'class_group_id' => null,
            'first_entry_date' => null,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonthNoOverflow()->subDay()->toDateString(),
            'entries_remaining' => null,
            'continuation_confirmed' => false,
        ];
    }

    public function endingInDays(int $days): static
    {
        return $this->state(fn (): array => [
            'end_date' => now()->addDays($days)->toDateString(),
        ]);
    }
}
