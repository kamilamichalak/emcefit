<?php

namespace App\Domain\Reservations\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Reservations\Enums\ReservationKind;
use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Support\PolishMonth;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Scheduling\Models\ClassSchedule;
use Carbon\CarbonImmutable;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected $attributes = [
        'kind' => 'stala',
        'late_release' => false,
    ];

    protected $fillable = [
        'client_id',
        'class_schedule_id',
        'membership_id',
        'status',
        'kind',
        'makeup_credit_id',
        'late_release',
        'reported_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'kind' => ReservationKind::class,
            'late_release' => 'boolean',
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

    /** Odrobienie wykorzystane na to miejsce (gdy kind = odrobienie). */
    public function consumedCredit(): BelongsTo
    {
        return $this->belongsTo(MakeupCredit::class, 'makeup_credit_id');
    }

    /**
     * Za jakie zajęcia klientka odrabia, np. „pon 30.11, 17:55 Funkcjonal Choreo Step”.
     * Null, gdy to nie odrobienie albo źródła nie da się ustalić.
     */
    public function makeupSourceLabel(): ?string
    {
        $source = $this->consumedCredit?->sourceReservation?->classSchedule;

        if ($this->kind !== ReservationKind::Makeup || $source === null) {
            return null;
        }

        return $source->date->translatedFormat('D j.m').', '.CarbonImmutable::parse($source->start_time)->format('H:i')
            .' '.$source->classGroup->classType->name;
    }

    /**
     * Co się stało z odrobieniem przy zwolnionym miejscu — opis dla klientki:
     * „odrobione: czw 19.11, 20:05 Body Pump”, „do odrobienia”, „odrobienie przepadło…”.
     */
    public function releaseNote(): ?string
    {
        if ($this->status !== ReservationStatus::Released) {
            return null;
        }

        if ($this->kind === ReservationKind::Makeup) {
            if (! $this->late_release) {
                return 'odrobienie wróciło — możesz wybrać inny termin';
            }

            $source = $this->consumedCredit?->sourceReservation?->classSchedule;

            return 'odrobienie'.($source ? ' za '.$source->date->translatedFormat('D j.m') : '').' przepadło (zwolnione później niż godzinę przed)';
        }

        if ($this->kind === ReservationKind::OpenEntry) {
            return $this->late_release ? 'wejście przepadło (zwolnione za późno)' : 'wejście wróciło na karnet';
        }

        $credit = $this->makeupCredit;

        if ($credit === null) {
            return 'bez odrobienia (zwolnione później niż godzinę przed)';
        }

        return $this->creditNote($credit);
    }

    /** Czy studio odwołało zajęcia, na które jest ta rezerwacja. */
    public function isClassCancelled(): bool
    {
        return $this->classSchedule->status === ClassOccurrenceStatus::Cancelled;
    }

    /**
     * Opis dla zapisu stałego na zajęcia odwołane przez studio (null, gdy nie dotyczy).
     */
    public function cancelledNote(): ?string
    {
        if (! $this->isClassCancelled() || $this->status === ReservationStatus::Released) {
            return null;
        }

        $reason = $this->classSchedule->cancellation_reason;
        $prefix = 'odwołane przez studio'.($reason ? " ({$reason})" : '');
        $credit = $this->kind === ReservationKind::Fixed ? $this->makeupCredit : null;

        return $credit ? $prefix.' — '.$this->creditNote($credit) : $prefix;
    }

    private function creditNote(MakeupCredit $credit): string
    {
        if (! $credit->used) {
            // wygasłe tylko odrobienie z minionego miesiąca; za zajęcia w kolejnym miesiącu
            // („do odrobienia w grudniu”) jeszcze nie działa, ale nie wygasło
            $expired = $credit->expires_end_of_month
                && $credit->created_at !== null
                && $credit->created_at->lt(now()->startOfMonth());

            return $expired ? 'odrobienie wygasło' : 'do odrobienia '.PolishMonth::in($credit->created_at);
        }

        $consumer = $credit->consumer;
        $where = $consumer?->classSchedule
            ? $consumer->classSchedule->date->translatedFormat('D j.m').', '.CarbonImmutable::parse($consumer->classSchedule->start_time)->format('H:i').' '.$consumer->classSchedule->classGroup->classType->name
            : null;

        if ($consumer?->status === ReservationStatus::Released) {
            return 'odrobienie przepadło'.($where ? " (zwolniłaś odrabianie: {$where})" : '');
        }

        return 'odrobione'.($where ? ": {$where}" : '');
    }

    /**
     * Data i godzina rozpoczęcia zajęć, których dotyczy rezerwacja.
     */
    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->classSchedule->date->toDateString())
            ->setTimeFromTimeString($this->classSchedule->start_time);
    }
}
