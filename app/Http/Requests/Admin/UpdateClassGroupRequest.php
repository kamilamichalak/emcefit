<?php

namespace App\Http\Requests\Admin;

class UpdateClassGroupRequest extends StoreClassGroupRequest
{
    /**
     * Edycja nie zmienia miesiaca obowiazywania — pole `month` nie jest wymagane.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['month']);

        return $rules;
    }
}
