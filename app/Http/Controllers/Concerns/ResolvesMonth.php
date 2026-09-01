<?php

namespace App\Http\Controllers\Concerns;

use Carbon\CarbonImmutable;

trait ResolvesMonth
{
    protected function resolveMonth(mixed $input): CarbonImmutable
    {
        if (is_string($input) && preg_match('/^\d{4}-\d{2}$/', $input) === 1) {
            try {
                return CarbonImmutable::parse($input.'-01')->startOfMonth();
            } catch (\Throwable) {
                // nieprawidlowa wartosc — biezacy miesiac ponizej
            }
        }

        return CarbonImmutable::today()->startOfMonth();
    }

    /**
     * @return array<string, string>
     */
    protected function presentMonth(CarbonImmutable $month): array
    {
        return [
            'value' => $month->format('Y-m'),
            'label' => $month->translatedFormat('LLLL Y'),
            'prev' => $month->subMonthNoOverflow()->format('Y-m'),
            'next' => $month->addMonthNoOverflow()->format('Y-m'),
        ];
    }
}
