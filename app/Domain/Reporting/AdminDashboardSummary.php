<?php

namespace App\Domain\Reporting;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Domain\Reservations\Models\MakeupCredit;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Models\ClassSchedule;
use Carbon\CarbonImmutable;

/**
 * Snapshot dla pulpitu admina (spec sekcja 22) — płatności, cykl miesiąca,
 * obłożenie zajęć i konta klientów.
 */
final class AdminDashboardSummary
{
    /** Poniżej tego zapełnienia wystąpienie trafia na listę "niskiego obłożenia". */
    private const LOW_OCCUPANCY_RATIO = 0.30;

    /** Ile dni do końca miesiąca uznajemy za "niedługo wygasa" dla makeup_credits. */
    private const MAKEUP_EXPIRY_WARN_DAYS = 7;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $today = CarbonImmutable::today();
        $now = CarbonImmutable::now();
        $upcoming = $today->addMonthNoOverflow()->startOfMonth();
        $enrollmentOpen = EnrollmentWindow::isOpenFor($upcoming);

        // 1. Niezaksięgowane płatności — najdłużej czekające najwyżej.
        $pendingPayments = Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->with('client.user:id,name')
            ->get()
            ->map(function (Payment $payment) use ($today): array {
                $reported = $payment->reported_date ?? $payment->created_at;

                return [
                    'id' => $payment->id,
                    'client_id' => $payment->client_id,
                    'client_name' => $payment->client->user->name,
                    'amount' => $payment->amount,
                    'reported_date' => $payment->reported_date?->toDateString(),
                    'transfer_title' => $payment->transfer_title,
                    'days_waiting' => $reported ? (int) $reported->startOfDay()->diffInDays($today, false) : 0,
                ];
            })
            ->sortByDesc('days_waiting')
            ->values();

        // 2. Rezerwacje bez opłaty, a zajęcia startują w ciągu 24h — sygnał ostrzegawczy.
        $unpaidSoon = Reservation::query()
            ->where('status', ReservationStatus::PendingPayment)
            ->whereHas('classSchedule', fn ($q) => $q->whereBetween('date', [
                $today->toDateString(),
                $today->addDay()->toDateString(),
            ]))
            ->with(['client.user:id,name', 'classSchedule.classGroup.classType:id,name,color,icon'])
            ->get()
            ->filter(function (Reservation $reservation) use ($now): bool {
                $startsAt = $reservation->startsAt();

                return $startsAt->isBetween($now, $now->addDay());
            })
            ->sortBy(fn (Reservation $reservation) => $reservation->startsAt()->getTimestamp())
            ->map(function (Reservation $reservation) use ($now): array {
                $startsAt = $reservation->startsAt();
                $type = $reservation->classSchedule->classGroup->classType;

                return [
                    'id' => $reservation->id,
                    'client_id' => $reservation->client_id,
                    'client_name' => $reservation->client->user->name,
                    'type_name' => $type->name,
                    'type_color' => $type->color,
                    'type_icon' => $type->icon,
                    'starts_at_label' => $startsAt->translatedFormat('D, j F, H:i'),
                    'hours_left' => (int) ceil($now->diffInMinutes($startsAt, false) / 60),
                ];
            })
            ->values();

        // 4. Aktywni klienci bez zgłoszenia na nadchodzący miesiąc (tylko gdy zapisy otwarte).
        $notEnrolled = collect();
        if ($enrollmentOpen) {
            $notEnrolled = Client::query()
                ->where('status', ClientStatus::Active)
                ->whereDoesntHave('memberships', fn ($q) => $q->whereDate('start_date', $upcoming->toDateString()))
                ->with('user:id,name')
                ->get()
                ->map(fn (Client $client): array => [
                    'id' => $client->id,
                    'name' => $client->user->name,
                ])
                ->values();
        }

        // 5. Zajęcia z niskim obłożeniem w najbliższych 7 dniach.
        $weekOut = $today->addDays(7);
        $lowOccupancy = ClassSchedule::query()
            ->where('status', ClassOccurrenceStatus::Planned)
            ->whereBetween('date', [$today->toDateString(), $weekOut->toDateString()])
            ->with(['classGroup.classType:id,name,color,icon', 'reservations'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(function (ClassSchedule $occurrence): array {
                $capacity = (int) $occurrence->classGroup->capacity;
                $confirmed = $occurrence->reservations
                    ->where('status', ReservationStatus::Confirmed)->count();
                $type = $occurrence->classGroup->classType;

                return [
                    'id' => $occurrence->id,
                    'type_name' => $type->name,
                    'type_color' => $type->color,
                    'type_icon' => $type->icon,
                    'date_label' => $occurrence->date->translatedFormat('D, j F').', '.$occurrence->startsAt(),
                    'confirmed' => $confirmed,
                    'capacity' => $capacity,
                    'ratio' => $capacity > 0 ? $confirmed / $capacity : 1.0,
                ];
            })
            ->filter(fn (array $row): bool => $row['capacity'] > 0 && $row['ratio'] < self::LOW_OCCUPANCY_RATIO)
            ->sortBy('ratio')
            ->values();

        // 6. Lista oczekujących łącznie, z rozbiciem na nadchodzące zajęcia.
        $waitlist = Reservation::query()
            ->where('status', ReservationStatus::Waitlist)
            ->whereHas('classSchedule', fn ($q) => $q->whereDate('date', '>=', $today->toDateString()))
            ->with('classSchedule.classGroup.classType:id,name')
            ->get()
            ->groupBy('class_schedule_id')
            ->map(function ($group) {
                $occurrence = $group->first()->classSchedule;

                return [
                    'label' => $occurrence->date->translatedFormat('D, j F').' — '
                        .$occurrence->classGroup->classType->name,
                    'date' => $occurrence->date->toDateString(),
                    'count' => $group->count(),
                ];
            })
            ->sortBy('date')
            ->values();

        // 7. Klienci bez aktywowanego konta (nie kliknęli linku aktywacyjnego).
        $withoutLogin = Client::query()
            ->whereNull('invitation_used_at')
            ->with('user:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->user->name,
                'join_date' => $client->join_date?->toDateString(),
            ])
            ->values();

        // 8. Zajęcia do odrobienia, które wygasną z końcem miesiąca (mało dni zostało).
        $daysToMonthEnd = (int) $today->diffInDays($today->endOfMonth(), false);
        $makeupExpiring = collect();
        if ($daysToMonthEnd <= self::MAKEUP_EXPIRY_WARN_DAYS) {
            $makeupExpiring = MakeupCredit::query()
                ->available()
                ->where('expires_end_of_month', true)
                ->with('client.user:id,name')
                ->get()
                ->groupBy('client_id')
                ->map(fn ($credits): array => [
                    'client_id' => $credits->first()->client_id,
                    'client_name' => $credits->first()->client->user->name,
                    'count' => $credits->count(),
                ])
                ->sortByDesc('count')
                ->values();
        }

        return [
            'pendingPayments' => $pendingPayments,
            'pendingPaymentsTotal' => round((float) $pendingPayments->sum('amount'), 2),
            'unpaidSoon' => $unpaidSoon,
            'enrollmentUpcoming' => [
                'value' => $upcoming->format('Y-m'),
                'label' => $upcoming->translatedFormat('F Y'),
                'open' => $enrollmentOpen,
            ],
            'clientsNotEnrolled' => $notEnrolled,
            'lowOccupancy' => $lowOccupancy,
            'waitlist' => $waitlist,
            'waitlistTotal' => $waitlist->sum('count'),
            'clientsWithoutLogin' => $withoutLogin,
            'makeupExpiring' => $makeupExpiring,
            'clientsActive' => Client::where('status', ClientStatus::Active)->count(),
            'clientsTotal' => Client::count(),
        ];
    }
}
