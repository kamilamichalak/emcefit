<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Scheduling\Enums\Weekday;
use App\Domain\Trainers\Models\Trainer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassGroup extends Model
{
    protected $fillable = [
        'class_type_id',
        'trainer_id',
        'weekday',
        'start_time',
        'duration_minutes',
        'capacity',
        'active_from',
        'active_to',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
            'duration_minutes' => 'integer',
            'capacity' => 'integer',
            'active_from' => 'date',
            'active_to' => 'date',
        ];
    }

    public function classType(): BelongsTo
    {
        return $this->belongsTo(ClassType::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }

    /**
     * Wzorce obowiazujace w miesiacu wskazanym przez dowolna date z tego miesiaca
     * (porownanie po 1. dniu miesiaca).
     */
    public function scopeActiveForMonth(Builder $query, CarbonInterface $month): Builder
    {
        $firstOfMonth = $month->copy()->startOfMonth()->toDateString();

        return $query
            ->whereDate('active_from', '<=', $firstOfMonth)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('active_to')
                ->orWhereDate('active_to', '>=', $firstOfMonth));
    }
}
