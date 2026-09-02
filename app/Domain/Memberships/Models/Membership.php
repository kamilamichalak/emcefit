<?php

namespace App\Domain\Memberships\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Models\ClassGroup;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'membership_type_id',
        'price_locked',
        'first_entry_date',
        'start_date',
        'end_date',
        'entries_remaining',
        'continuation_confirmed',
    ];

    protected function casts(): array
    {
        return [
            'price_locked' => 'decimal:2',
            'first_entry_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'entries_remaining' => 'integer',
            'continuation_confirmed' => 'boolean',
        ];
    }

    protected static function newFactory(): MembershipFactory
    {
        return MembershipFactory::new();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function membershipType(): BelongsTo
    {
        return $this->belongsTo(MembershipType::class);
    }

    /**
     * Zajecia cykliczne wybrane w ramach tego karnetu (tryb zamkniety) —
     * relacja wiele-do-wielu przez membership_class_groups.
     */
    public function classGroups(): BelongsToMany
    {
        return $this->belongsToMany(ClassGroup::class, 'membership_class_groups');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Karnet uznajemy za oplacony, gdy ma co najmniej jedna zaksiegowana platnosc.
     * Wymaga zaladowanej relacji `payments`.
     */
    public function isPaid(): bool
    {
        return $this->payments->contains(
            fn (Payment $payment): bool => $payment->status === PaymentStatus::Settled,
        );
    }

    public function hasPendingPayment(): bool
    {
        return $this->payments->contains(
            fn (Payment $payment): bool => $payment->status === PaymentStatus::Pending,
        );
    }
}
