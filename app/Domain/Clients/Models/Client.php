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
        'phone',
        'birth_date',
        'status',
        'join_date',
        'terms_accepted_at',
        'health_declaration_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'join_date' => 'date',
            'terms_accepted_at' => 'datetime',
            'health_declaration_at' => 'datetime',
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
