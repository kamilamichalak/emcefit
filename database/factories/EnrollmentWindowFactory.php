<?php

namespace Database\Factories;

use App\Domain\Reservations\Models\EnrollmentWindow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentWindow>
 */
class EnrollmentWindowFactory extends Factory
{
    protected $model = EnrollmentWindow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $month = CarbonImmutable::today();

        return [
            'year' => $month->year,
            'month' => $month->month,
            'open' => false,
            'opened_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => ['open' => true, 'opened_at' => now()]);
    }

    public function forMonth(CarbonImmutable $month): static
    {
        return $this->state(fn (): array => ['year' => $month->year, 'month' => $month->month]);
    }
}
