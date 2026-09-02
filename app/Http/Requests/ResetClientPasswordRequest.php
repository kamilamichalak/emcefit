<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetClientPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Dostępu strzeże podpisany link (weryfikowany w kontrolerze), nie logowanie.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }
}
