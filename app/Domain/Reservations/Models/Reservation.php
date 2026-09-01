<?php

namespace App\Domain\Reservations\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Scheduling\Models\ClassSchedule;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'class_schedule_id',
        'membership_id',
        'status',
        'reported_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'reported_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ReservationFactory
    {
        return ReservationFactory::new();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function makeupCredit(): HasOne
    {
        return $this->hasOne(MakeupCredit::class, 'source_reservation_id');
    }
}
