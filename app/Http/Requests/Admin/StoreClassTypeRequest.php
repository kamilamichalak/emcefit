<?php

namespace App\Http\Requests\Admin;

use App\Domain\Scheduling\Data\ClassTypeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('class_types', 'name')],
            'description' => ['nullable', 'string', 'max:2000'],
            'required_equipment' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): ClassTypeData
    {
        return new ClassTypeData(
            name: $this->string('name')->trim()->toString(),
            description: $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            requiredEquipment: $this->filled('required_equipment') ? $this->string('required_equipment')->trim()->toString() : null,
        );
    }
}
