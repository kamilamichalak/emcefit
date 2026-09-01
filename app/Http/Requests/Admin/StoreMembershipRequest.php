<?php

namespace App\Http\Requests\Admin;

use App\Domain\Memberships\Data\MembershipData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'membership_type_id' => ['required', 'integer', Rule::exists('membership_types', 'id')],
            'start_date' => ['nullable', 'date'],
            'first_entry_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'entries_remaining' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    public function toData(): MembershipData
    {
        return new MembershipData(
            membershipTypeId: $this->integer('membership_type_id'),
            startDate: $this->date('start_date')?->toDateString(),
            firstEntryDate: $this->date('first_entry_date')?->toDateString(),
            endDate: $this->date('end_date')?->toDateString(),
            entriesRemaining: $this->filled('entries_remaining')
                ? $this->integer('entries_remaining')
                : null,
        );
    }
}
