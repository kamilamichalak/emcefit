<?php

namespace App\Domain\Memberships\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membership extends Model
{
    protected $fillable = [
        'client_id',
        'membership_type_id',
        'class_group_id',
        'data_pierwszego_wejscia',
        'data_od',
        'data_do',
        'wejscia_pozostale',
        'kontynuacja_potwierdzona',
    ];

    protected function casts(): array
    {
        return [
            'data_pierwszego_wejscia' => 'date',
            'data_od' => 'date',
            'data_do' => 'date',
            'wejscia_pozostale' => 'integer',
            'kontynuacja_potwierdzona' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function membershipType(): BelongsTo
    {
        return $this->belongsTo(MembershipType::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
