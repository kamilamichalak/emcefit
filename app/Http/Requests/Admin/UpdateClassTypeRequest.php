<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateClassTypeRequest extends StoreClassTypeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $classType = $this->route('classType');

        return [
            ...parent::rules(),
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('class_types', 'name')->ignore($classType->id),
            ],
        ];
    }
}
