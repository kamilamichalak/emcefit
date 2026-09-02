<?php

namespace App\Http\Requests\Client;

use App\Domain\Clients\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Admin zapisujący klienta w jego imieniu (Prompt 17) — trasa z parametrem {client}.
        if ($this->route('client') instanceof Client) {
            return $this->user()?->hasRole('admin') ?? false;
        }

        return $this->user()?->hasRole('client') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'class_group_ids' => ['required', 'array', 'min:1'],
            'class_group_ids.*' => ['integer', Rule::exists('class_groups', 'id')],
            'absences' => ['array'],
            'absences.*' => ['integer'],
        ];
    }

    /**
     * @return list<int>
     */
    public function classGroupIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->input('class_group_ids', []))));
    }

    /**
     * @return list<int>
     */
    public function absences(): array
    {
        return array_values(array_unique(array_map('intval', $this->input('absences', []))));
    }
}
