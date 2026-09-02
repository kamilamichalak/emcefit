<?php

namespace App\Domain\Clients\Models;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Models\Payment;
use App\Domain\Reservations\Models\MakeupCredit;
use App\Domain\Reservations\Models\Reservation;
use App\Models\User;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'birth_date',
        'status',
        'join_date',
        'terms_accepted_at',
        'health_declaration_at',
        'invitation_used_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'join_date' => 'date',
            'terms_accepted_at' => 'datetime',
            'health_declaration_at' => 'datetime',
            'invitation_used_at' => 'datetime',
            'status' => ClientStatus::class,
        ];
    }

    public function isActivated(): bool
    {
        return $this->invitation_used_at !== null;
    }

    /**
     * Model zyje poza App\Models, wiec wskazujemy fabryke jawnie.
     */
    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
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

    public function makeupCredits(): HasMany
    {
        return $this->hasMany(MakeupCredit::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
