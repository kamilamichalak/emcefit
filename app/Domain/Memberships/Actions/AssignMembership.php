<?php

namespace App\Domain\Memberships\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Data\MembershipData;
use App\Domain\Memberships\Enums\ValidityPeriodType;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use Carbon\CarbonImmutable;

final class AssignMembership
{
    public function handle(Client $client, MembershipData $data): Membership
    {
        $type = MembershipType::findOrFail($data->membershipTypeId);

        $startDate = $data->startDate ?? CarbonImmutable::today()->toDateString();

        return $client->memberships()->create([
            'membership_type_id' => $type->id,
            'start_date' => $startDate,
            'first_entry_date' => $data->firstEntryDate,
            'end_date' => $data->endDate ?? $this->resolveEndDate($type, $startDate, $data->firstEntryDate),
            'entries_remaining' => $data->entriesRemaining ?? $type->defaultEntries(),
        ]);
    }

    /**
     * Data konca liczona od pierwszego wejscia (jesli typ tak liczy waznosc i data
     * jest znana) albo od startu karnetu. Null, gdy nie da sie wyliczyc.
     */
    private function resolveEndDate(MembershipType $type, string $startDate, ?string $firstEntryDate): ?string
    {
        $basis = $type->validity_period_type === ValidityPeriodType::WeeksFromFirstEntry
            ? $firstEntryDate
            : $startDate;

        if ($basis === null) {
            return null;
        }

        return $type->resolveEndDate(CarbonImmutable::parse($basis))?->toDateString();
    }
}
