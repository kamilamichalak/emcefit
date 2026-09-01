<?php

namespace App\Domain\Clients\Models;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'user_id',
        'telefon',
        'data_urodzenia',
        'status',
        'data_dolaczenia',
        'regulamin_zaakceptowany_at',
        'oswiadczenie_zdrowotne_at',
    ];

    protected function casts(): array
    {
        return [
            'data_urodzenia' => 'date',
            'data_dolaczenia' => 'date',
            'regulamin_zaakceptowany_at' => 'datetime',
            'oswiadczenie_zdrowotne_at' => 'datetime',
            'status' => ClientStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
