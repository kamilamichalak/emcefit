<?php

namespace App\Domain\Memberships\Models;

use App\Domain\Memberships\Enums\MembershipMode;
use App\Domain\Memberships\Enums\ValidityPeriodType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipType extends Model
{
    protected $fillable = [
        'nazwa',
        'tryb',
        'sesje_w_tygodniu',
        'liczba_wejsc',
        'okres_waznosci_typ',
        'okres_waznosci_wartosc',
        'cena',
    ];

    protected function casts(): array
    {
        return [
            'tryb' => MembershipMode::class,
            'okres_waznosci_typ' => ValidityPeriodType::class,
            'sesje_w_tygodniu' => 'integer',
            'liczba_wejsc' => 'integer',
            'okres_waznosci_wartosc' => 'integer',
            'cena' => 'decimal:2',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
