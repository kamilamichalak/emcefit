<?php

namespace App\Domain\Memberships\Data;

/**
 * Dane do przypisania karnetu klientowi. Daty jako 'Y-m-d' albo null.
 * Pola null oznaczaja "wylicz domyslnie" (data konca, pula wejsc).
 */
final readonly class MembershipData
{
    public function __construct(
        public int $membershipTypeId,
        public ?string $startDate,
        public ?string $firstEntryDate,
        public ?string $endDate,
        public ?int $entriesRemaining,
    ) {}
}
