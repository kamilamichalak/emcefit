<?php

namespace App\Http\Requests\Admin;

use App\Domain\Scheduling\Data\ClassGroupData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassGroupRequest extends FormRequest
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
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'class_type_id' => ['required', 'integer', Rule::exists('class_types', 'id')],
            'trainer_id' => ['nullable', 'integer', Rule::exists('trainers', 'id')],
            'weekday' => ['required', 'integer', 'between:1,5'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }

    public function month(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('month')->toString().'-01')->startOfMonth();
    }

    public function toData(): ClassGroupData
    {
        return new ClassGroupData(
            classTypeId: $this->integer('class_type_id'),
            trainerId: $this->filled('trainer_id') ? $this->integer('trainer_id') : null,
            weekday: $this->integer('weekday'),
            startTime: $this->string('start_time')->toString(),
            durationMinutes: $this->integer('duration_minutes'),
            capacity: $this->integer('capacity'),
        );
    }
}
