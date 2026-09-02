<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeMembershipRequest extends FormRequest
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
            'membership_type_id' => ['required', Rule::exists('membership_types', 'id')],
            'price' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function price(): string
    {
        return number_format((float) $this->validated('price'), 2, '.', '');
    }

    public function note(): ?string
    {
        $note = trim((string) $this->input('note'));

        return $note === '' ? null : $note;
    }
}
