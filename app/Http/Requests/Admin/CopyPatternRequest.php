<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CopyPatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * `month` = miesiac DOCELOWY, do ktorego kopiujemy wzorzec.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'force' => ['boolean'],
        ];
    }
}
