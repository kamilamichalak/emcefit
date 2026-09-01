<?php

namespace App\Domain\Memberships\Data;

use App\Domain\Memberships\Models\MembershipType;
use Carbon\CarbonImmutable;

/**
 * Wynik dopasowania wariantu karnetu zamkniętego dla zgłoszenia klienta na miesiąc
 * (Prompt 10a/10b/10e). `type` = null oznacza, że cennik nie ma nawet wariantu
 * miesięcznego dla tej liczby zajęć/tydzień.
 */
final readonly class ResolvedVariant
{
    public function __construct(
        public ?MembershipType $type,
        public ?CarbonImmutable $firstEntryDate,
        public ?CarbonImmutable $endDate,
        public int $attendanceWeeks,
        public int $totalWeeks,
        /** true, gdy zastosowano krótszy wariant (tygodnie od pierwszego wejścia) */
        public bool $shortened,
        /** true, gdy klient pominął całe tygodnie, ale brak pasującego krótszego pakietu */
        public bool $fellBackToMonthly,
    ) {}
}
