<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CancelClassOccurrenceRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:255'],
            'notify' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Podaj krótki powód odwołania zajęć.',
        ];
    }

    /** Czy powiadomić zapisane klientki mailem — domyślnie NIE (Majka zwykle uprzedza na grupie). */
    public function notify(): bool
    {
        return $this->boolean('notify');
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->toString();
    }
}
