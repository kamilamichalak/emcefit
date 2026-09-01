<?php

namespace App\Domain\Payments\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'client_id',
        'membership_id',
        'amount',
        'reported_date',
        'settled_date',
        'status',
        'transfer_title',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reported_date' => 'date',
            'settled_date' => 'date',
            'status' => PaymentStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
