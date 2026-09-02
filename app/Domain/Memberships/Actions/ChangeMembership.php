<?php

namespace App\Domain\Memberships\Actions;

use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use App\Models\User;

/**
 * Ręczna zmiana typu i/lub ceny istniejącego karnetu przez admina (Prompt 16e) —
 * np. indywidualny rabat. Płatności zostają nietknięte (rozliczane ręcznie).
 */
final class ChangeMembership
{
    public function handle(Membership $membership, MembershipType $type, string $price, ?string $note, User $admin): Membership
    {
        $membership->update([
            'membership_type_id' => $type->id,
            'price_locked' => $price,
            'modified_by_id' => $admin->id,
            'modified_at' => now(),
            'admin_note' => $note,
        ]);

        return $membership->fresh(['membershipType', 'modifiedBy']);
    }
}
