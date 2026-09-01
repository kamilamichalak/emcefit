<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use Carbon\Carbon;
use Database\Factories\ClassScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSchedule extends Model
{
    /** @use HasFactory<ClassScheduleFactory> */
    use HasFactory;

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

    protected static function newFactory(): ClassScheduleFactory
    {
        return ClassScheduleFactory::new();
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** Godzina rozpoczecia w formacie H:i. */
    public function startsAt(): string
    {
        return Carbon::parse($this->start_time)->format('H:i');
    }

    public function isCancelled(): bool
    {
        return $this->status === ClassOccurrenceStatus::Cancelled;
    }
}
