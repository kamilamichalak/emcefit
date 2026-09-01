<?php

namespace App\Http\Requests\Admin;

use App\Domain\Clients\Data\ClientData;
use App\Domain\Clients\Enums\ClientStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'status' => ['required', Rule::enum(ClientStatus::class)],
            'join_date' => ['nullable', 'date'],
            'terms_accepted' => ['boolean'],
            'health_declaration' => ['boolean'],
        ];
    }

    public function toData(): ClientData
    {
        return new ClientData(
            name: $this->string('name')->trim()->toString(),
            email: $this->string('email')->trim()->lower()->toString(),
            password: $this->filled('password') ? $this->string('password')->toString() : null,
            phone: $this->filled('phone') ? $this->string('phone')->trim()->toString() : null,
            birthDate: $this->date('birth_date')?->toDateString(),
            status: ClientStatus::from($this->string('status')->toString()),
            joinDate: $this->date('join_date')?->toDateString(),
            termsAccepted: $this->boolean('terms_accepted'),
            healthDeclaration: $this->boolean('health_declaration'),
        );
    }
}
