<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Memberships\Models\Membership;
use App\Domain\Scheduling\Enums\Weekday;
use App\Domain\Trainers\Models\Trainer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\ClassGroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassGroup extends Model
{
    /** @use HasFactory<ClassGroupFactory> */
    use HasFactory;

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

    protected static function newFactory(): ClassGroupFactory
    {
        return ClassGroupFactory::new();
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
     * Karnety, w ramach ktorych klient wybral te zajecia (przez membership_class_groups).
     */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class, 'membership_class_groups');
    }

    /** Godzina rozpoczecia w formacie H:i. */
    public function startsAt(): string
    {
        return Carbon::parse($this->start_time)->format('H:i');
    }

    /** Godzina zakonczenia (start + czas trwania) w formacie H:i. */
    public function endsAt(): string
    {
        return Carbon::parse($this->start_time)->addMinutes($this->duration_minutes)->format('H:i');
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
