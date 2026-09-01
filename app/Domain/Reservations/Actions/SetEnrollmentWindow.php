<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\Models\EnrollmentWindow;
use Carbon\CarbonInterface;

final class SetEnrollmentWindow
{
    /**
     * Otwiera albo zamyka zapisy klientów na wskazany miesiąc. Przy otwarciu
     * zapisuje `opened_at` = teraz; przy zamknięciu zostawia poprzedni znacznik.
     */
    public function handle(CarbonInterface $month, bool $open): EnrollmentWindow
    {
        return EnrollmentWindow::updateOrCreate(
            ['year' => $month->year, 'month' => $month->month],
            $open ? ['open' => true, 'opened_at' => now()] : ['open' => false],
        );
    }
}
