<?php

namespace App\Domain\Reservations\Models;

use App\Domain\Clients\Models\Client;
use Database\Factories\MakeupCreditFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MakeupCredit extends Model
{
    /** @use HasFactory<MakeupCreditFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'source_reservation_id',
        'expires_end_of_month',
        'used',
    ];

    protected function casts(): array
    {
        return [
            'expires_end_of_month' => 'boolean',
            'used' => 'boolean',
        ];
    }

    protected static function newFactory(): MakeupCreditFactory
    {
        return MakeupCreditFactory::new();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sourceReservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'source_reservation_id');
    }

    /**
     * Kredyty do wykorzystania: niewykorzystane i (bezterminowe albo jeszcze
     * nie po końcu bieżącego miesiąca).
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('used', false)
            ->where(fn (Builder $inner) => $inner
                ->where('expires_end_of_month', false)
                ->orWhere('created_at', '>=', now()->startOfMonth()));
    }
}
