<?php

namespace App\Http\Requests\Admin;

use App\Domain\Clients\Data\ClientData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date', 'before:today'],
        ];
    }

    public function toData(): ClientData
    {
        return new ClientData(
            name: $this->string('name')->trim()->toString(),
            email: $this->string('email')->trim()->lower()->toString(),
            phone: $this->filled('phone') ? $this->string('phone')->trim()->toString() : null,
            birthDate: $this->date('birth_date')?->toDateString(),
        );
    }
}
