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
