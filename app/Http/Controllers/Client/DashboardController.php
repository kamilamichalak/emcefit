<?php

namespace App\Http\Controllers\Client;

use App\Domain\Memberships\Models\Membership;
use App\Domain\Reservations\Enums\ReservationKind;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\FreeSpots;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Domain\Reservations\Models\Reservation;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pierwszy ekran klientki: najbliższe zajęcia, karnet na ten miesiąc i jasne „co dalej”.
 */
class DashboardController extends Controller
{
    private const UPCOMING_LIMIT = 3;

    public function index(Request $request, FreeSpots $freeSpots): Response
    {
        $client = $request->user()->client;
        $thisMonth = CarbonImmutable::today()->startOfMonth();
        $nextMonth = $thisMonth->addMonthNoOverflow();

        $memberships = $client
            ? $client->memberships()
                ->whereIn('start_date', [$thisMonth->toDateString(), $nextMonth->toDateString()])
                ->with(['membershipType:id,name', 'payments', 'reservations.classSchedule.classGroup.classType:id,name,color,icon', 'reservations.consumedCredit.sourceReservation.classSchedule.classGroup.classType'])
                ->get()
            : collect();

        $current = $memberships->first(fn (Membership $m) => $m->start_date->equalTo($thisMonth));
        $next = $memberships->first(fn (Membership $m) => $m->start_date->equalTo($nextMonth));

        $upcoming = $memberships
            ->flatMap(fn (Membership $m) => $m->reservations)
            ->filter(fn (Reservation $r) => in_array($r->status, [
                ReservationStatus::Confirmed,
                ReservationStatus::PendingPayment,
                ReservationStatus::Waitlist,
            ], true) && $r->startsAt()->isFuture())
            ->sortBy(fn (Reservation $r) => $r->startsAt()->getTimestamp())
            ->take(self::UPCOMING_LIMIT)
            ->map(fn (Reservation $r): array => [
                'id' => $r->id,
                'date' => Str::ucfirst($r->classSchedule->date->translatedFormat('l, j F')),
                'start_time' => $r->classSchedule->startsAt(),
                'end_time' => CarbonImmutable::parse($r->classSchedule->start_time)
                    ->addMinutes($r->classSchedule->classGroup->duration_minutes)->format('H:i'),
                'type_name' => $r->classSchedule->classGroup->classType->name,
                'type_color' => $r->classSchedule->classGroup->classType->color,
                'type_icon' => $r->classSchedule->classGroup->classType->icon,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'makeup_for' => $r->makeupSourceLabel(),
                'starts_at' => $r->startsAt()->toIso8601String(),
                'kind' => $r->kind->value,
                'cancellable' => $r->status === ReservationStatus::Confirmed,
                'leavable' => $r->status === ReservationStatus::Waitlist && $r->kind !== ReservationKind::Fixed,
            ])
            ->values();

        return Inertia::render('Client/Dashboard', [
            'firstName' => Str::before($request->user()->name, ' '),
            'upcoming' => $upcoming,
            'current' => $current ? $this->presentMembership($current, $thisMonth) : null,
            'next' => [
                'value' => $nextMonth->format('Y-m'),
                'label' => $nextMonth->translatedFormat('F'),
                'enrollment_open' => EnrollmentWindow::isOpenFor($nextMonth),
                'membership' => $next ? $this->presentMembership($next, $nextMonth) : null,
            ],
            'makeupCredits' => $client
                ? $client->makeupCredits()->available()->count()
                : 0,
            'openRemaining' => $client ? ($freeSpots->openEntitlement($client)['remaining'] ?? 0) : 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMembership(Membership $membership, CarbonImmutable $month): array
    {
        return [
            'month_label' => $month->translatedFormat('F'),
            'type_name' => $membership->membershipType->name,
            'paid' => $membership->isPaid(),
            'payment_label' => $membership->isPaid()
                ? 'opłacony'
                : ($membership->hasPendingPayment() ? 'czeka na wpłatę' : 'bez płatności'),
        ];
    }
}
