<?php

namespace App\Domain\Reporting;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Domain\Reservations\Models\Reservation;
use Carbon\CarbonImmutable;

/**
 * Snapshot dla pulpitu admina (spec sekcja 22) — płatności i cykl miesiąca.
 */
final class AdminDashboardSummary
{
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
            'clientsActive' => Client::where('status', ClientStatus::Active)->count(),
            'clientsTotal' => Client::count(),
        ];
    }
}
