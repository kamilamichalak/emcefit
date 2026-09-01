<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ActivateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Dostep chroni podpisany link (weryfikowany w kontrolerze), nie logowanie.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms_accepted' => ['accepted'],
            'health_declaration' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms_accepted.accepted' => 'Musisz zaakceptować regulamin, aby kontynuować.',
            'health_declaration.accepted' => 'Musisz złożyć oświadczenie o braku przeciwwskazań zdrowotnych.',
        ];
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }
}
