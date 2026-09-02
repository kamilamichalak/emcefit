<?php

namespace App\Http\Requests\Admin;

use App\Domain\Scheduling\Data\ClassTypeData;
use App\Domain\Scheduling\Models\ClassType;
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
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['required', 'string', Rule::in(ClassType::ICONS)],
            'default_capacity' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'color.regex' => 'Kolor musi być w formacie hex, np. #E91E63.',
            'icon.in' => 'Wybierz ikonę z listy.',
        ];
    }

    public function toData(): ClassTypeData
    {
        return new ClassTypeData(
            name: $this->string('name')->trim()->toString(),
            description: $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            requiredEquipment: $this->filled('required_equipment') ? $this->string('required_equipment')->trim()->toString() : null,
            color: strtoupper($this->string('color')->toString()),
            icon: $this->string('icon')->toString(),
            defaultCapacity: $this->integer('default_capacity'),
        );
    }
}
