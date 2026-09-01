<?php

namespace App\Domain\Memberships\Models;

use App\Domain\Memberships\Enums\MembershipMode;
use App\Domain\Memberships\Enums\ValidityPeriodType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipType extends Model
{
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

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
