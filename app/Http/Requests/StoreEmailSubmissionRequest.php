<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmailSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Podaj imię i nazwisko.',
            'email.required' => 'Podaj adres e-mail.',
            'email.email' => 'To nie wygląda na poprawny adres e-mail.',
            'consent.accepted' => 'Zaznacz zgodę, żebyśmy mogli zapisać Twoje dane.',
        ];
    }
}
