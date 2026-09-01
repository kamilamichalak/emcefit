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
        'kwota',
        'data_zgloszenia',
        'data_zaksiegowania',
        'status',
        'tytul_przelewu',
    ];

    protected function casts(): array
    {
        return [
            'kwota' => 'decimal:2',
            'data_zgloszenia' => 'date',
            'data_zaksiegowania' => 'date',
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
