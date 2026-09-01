<?php

namespace App\Domain\Memberships\Models;

use App\Domain\Memberships\Enums\MembershipMode;
use App\Domain\Memberships\Enums\ValidityPeriodType;
use Carbon\CarbonImmutable;
use Database\Factories\MembershipTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipType extends Model
{
    /** @use HasFactory<MembershipTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'mode',
        'sessions_per_week',
        'entry_count',
        'validity_period_type',
        'validity_period_value',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'mode' => MembershipMode::class,
            'validity_period_type' => ValidityPeriodType::class,
            'sessions_per_week' => 'integer',
            'entry_count' => 'integer',
            'validity_period_value' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    protected static function newFactory(): MembershipTypeFactory
    {
        return MembershipTypeFactory::new();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Wariant "abonament zamknięty, miesiąc kalendarzowy" dla podanej liczby
     * zajęć/tydzień — albo null, gdy cennik go nie przewiduje.
     */
    public static function monthlyClosedForSessions(int $sessionsPerWeek): ?self
    {
        return static::query()
            ->where('mode', MembershipMode::Closed)
            ->where('validity_period_type', ValidityPeriodType::CalendarMonth)
            ->where('sessions_per_week', $sessionsPerWeek)
            ->first();
    }

    /**
     * Krótszy wariant "abonament zamknięty, N tygodni od pierwszego wejścia" dla
     * podanej liczby zajęć/tydzień i liczby tygodni z obecnością — albo null, gdy
     * cennik nie ma takiego pakietu (Prompt 10e).
     */
    public static function closedForSessionsAndWeeks(int $sessionsPerWeek, int $weeks): ?self
    {
        return static::query()
            ->where('mode', MembershipMode::Closed)
            ->where('validity_period_type', ValidityPeriodType::WeeksFromFirstEntry)
            ->where('sessions_per_week', $sessionsPerWeek)
            ->where('validity_period_value', $weeks)
            ->first();
    }

    /**
     * Wylicza date konca waznosci karnetu na podstawie daty bazowej (start karnetu
     * albo data pierwszego wejscia — zaleznie od trybu liczenia). Zwraca null, gdy
     * typ nie ma zdefiniowanego okresu (np. wejscie jednorazowe).
     */
    public function resolveEndDate(CarbonImmutable $from): ?CarbonImmutable
    {
        if ($this->validity_period_type === null || $this->validity_period_value === null) {
            return null;
        }

        return match ($this->validity_period_type) {
            ValidityPeriodType::CalendarMonth => $from->addMonthsNoOverflow($this->validity_period_value)->subDay(),
            ValidityPeriodType::WeeksFromFirstEntry => $from->addWeeks($this->validity_period_value)->subDay(),
        };
    }

    /**
     * Domyslna pula wejsc dla nowego karnetu tego typu (null = bez limitu / nie dotyczy).
     */
    public function defaultEntries(): ?int
    {
        return $this->mode->tracksEntries() ? $this->entry_count : null;
    }
}
