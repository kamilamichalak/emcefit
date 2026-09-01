<?php

namespace Database\Factories;

use App\Domain\Memberships\Enums\MembershipMode;
use App\Domain\Memberships\Enums\ValidityPeriodType;
use App\Domain\Memberships\Models\MembershipType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipType>
 */
class MembershipTypeFactory extends Factory
{
    protected $model = MembershipType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Zamknięty 2x/tydzień — miesięczny',
            'mode' => MembershipMode::Closed,
            'sessions_per_week' => 2,
            'entry_count' => null,
            'validity_period_type' => ValidityPeriodType::CalendarMonth,
            'validity_period_value' => 1,
            'price' => 160.00,
        ];
    }

    public function open(int $entries = 4): static
    {
        return $this->state(fn (): array => [
            'name' => "Otwarty — {$entries} wejścia (5 tygodni)",
            'mode' => MembershipMode::Open,
            'sessions_per_week' => null,
            'entry_count' => $entries,
            'validity_period_type' => ValidityPeriodType::WeeksFromFirstEntry,
            'validity_period_value' => 5,
            'price' => 80.00,
        ]);
    }

    public function unlimited(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Bez limitu — miesięczny',
            'mode' => MembershipMode::Unlimited,
            'sessions_per_week' => null,
            'entry_count' => null,
            'validity_period_type' => ValidityPeriodType::CalendarMonth,
            'validity_period_value' => 1,
            'price' => 250.00,
        ]);
    }

    public function singleEntry(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Wejście jednorazowe',
            'mode' => MembershipMode::SingleEntry,
            'sessions_per_week' => null,
            'entry_count' => 1,
            'validity_period_type' => null,
            'validity_period_value' => null,
            'price' => 40.00,
        ]);
    }
}
