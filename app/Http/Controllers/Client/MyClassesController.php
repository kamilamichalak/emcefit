<?php

namespace App\Http\Controllers\Client;

use App\Domain\Memberships\Models\Membership;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Http\Controllers\Concerns\ResolvesMonth;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyClassesController extends Controller
{
    use ResolvesMonth;

    public function index(Request $request): Response
    {
        $client = $request->user()->client;
        $month = $this->targetMonth($request->query('month'));

        $membership = $client?->memberships()
            ->whereDate('start_date', $month->startOfMonth()->toDateString())
            ->with([
                'membershipType:id,name,price',
                'classGroups.classType:id,name,color,icon',
                'payments',
                'reservations.classSchedule.classGroup.classType:id,name,color,icon',
            ])
            ->first();

        return Inertia::render('Client/MyClasses', [
            'month' => ['value' => $month->format('Y-m'), 'label' => $month->translatedFormat('F Y')],
            'monthOptions' => $this->monthOptions(),
            'makeupCredits' => $client
                ? $client->makeupCredits()->available()->count()
                : 0,
            'membership' => $membership ? $this->presentMembership($membership) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMembership(Membership $membership): array
    {
        return [
            'type_name' => $membership->membershipType->name,
            'price' => $membership->price_locked,
            'start_date' => $membership->start_date->toDateString(),
            'end_date' => $membership->end_date?->toDateString(),
            'payment_status' => $membership->isPaid()
                ? 'zaksiegowana'
                : ($membership->hasPendingPayment() ? 'oczekuje' : 'brak'),
            'payment_status_label' => $membership->isPaid()
                ? 'Zaksięgowana'
                : ($membership->hasPendingPayment() ? 'Oczekuje na zaksięgowanie' : 'Brak zarejestrowanej płatności'),
            'classes' => $membership->classGroups
                ->sortBy([['weekday', 'asc'], ['start_time', 'asc']])
                ->map(fn (ClassGroup $group): array => [
                    'weekday_label' => $group->weekday->label(),
                    'start_time' => $group->startsAt(),
                    'end_time' => $group->endsAt(),
                    'type_name' => $group->classType->name,
                    'type_color' => $group->classType->color,
                    'type_icon' => $group->classType->icon,
                ])->values(),
            'reservations' => $membership->reservations
                ->sortBy(fn (Reservation $reservation) => $reservation->classSchedule->date->toDateString().$reservation->classSchedule->start_time)
                ->map(function (Reservation $reservation): array {
                    $startsAt = $reservation->startsAt();

                    return [
                        'id' => $reservation->id,
                        'date' => $reservation->classSchedule->date->translatedFormat('D, j F'),
                        'start_time' => $reservation->classSchedule->startsAt(),
                        'starts_at' => $startsAt->toIso8601String(),
                        'is_past' => $startsAt->isPast(),
                        'type_name' => $reservation->classSchedule->classGroup->classType->name,
                        'type_color' => $reservation->classSchedule->classGroup->classType->color,
                        'type_icon' => $reservation->classSchedule->classGroup->classType->icon,
                        'status' => $reservation->status->value,
                        'status_label' => $reservation->status->label(),
                        'cancellable' => $reservation->status === ReservationStatus::Confirmed
                            && $startsAt->isFuture(),
                    ];
                })->values(),
        ];
    }

    private function targetMonth(mixed $input): CarbonImmutable
    {
        $thisMonth = CarbonImmutable::today()->startOfMonth();
        $nextMonth = $thisMonth->addMonthNoOverflow();

        return $this->resolveMonth($input)->equalTo($nextMonth) ? $nextMonth : $thisMonth;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function monthOptions(): array
    {
        $thisMonth = CarbonImmutable::today()->startOfMonth();
        $nextMonth = $thisMonth->addMonthNoOverflow();

        return [
            ['value' => $thisMonth->format('Y-m'), 'label' => $thisMonth->translatedFormat('F Y')],
            ['value' => $nextMonth->format('Y-m'), 'label' => $nextMonth->translatedFormat('F Y')],
        ];
    }
}
