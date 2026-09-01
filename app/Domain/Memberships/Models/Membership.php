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
        'first_entry_date',
        'start_date',
        'end_date',
        'entries_remaining',
        'continuation_confirmed',
    ];

    protected function casts(): array
    {
        return [
            'first_entry_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'entries_remaining' => 'integer',
            'continuation_confirmed' => 'boolean',
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
