<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSchedule extends Model
{
    protected $table = 'class_schedule';

    protected $fillable = [
        'class_group_id',
        'date',
        'start_time',
        'status',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => ClassOccurrenceStatus::class,
        ];
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }
}
