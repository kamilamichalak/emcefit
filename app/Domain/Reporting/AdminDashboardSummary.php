<?php

namespace App\Domain\Reporting;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Enums\MembershipMode;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use Carbon\CarbonImmutable;

/**
 * Prosty snapshot dla pulpitu admina (spec sekcja 3 Faza 1 + 8a).
 */
final class AdminDashboardSummary
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $today = CarbonImmutable::today();
        $weekAhead = $today->addDays(7);
        $monthStart = $today->startOfMonth();
        $monthEnd = $today->endOfMonth();

        $endingMemberships = Membership::query()
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today->toDateString(), $weekAhead->toDateString()])
            ->with(['client.user:id,name', 'membershipType:id,name'])
            ->orderBy('end_date')
            ->get()
            ->map(fn (Membership $membership): array => [
                'id' => $membership->id,
                'client_id' => $membership->client_id,
                'client_name' => $membership->client->user->name,
                'type_name' => $membership->membershipType->name,
                'end_date' => $membership->end_date->toDateString(),
                'days_left' => (int) $today->diffInDays($membership->end_date, false),
            ]);

        $pendingPayments = Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->with(['client.user:id,name'])
            ->orderBy('reported_date')
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'client_id' => $payment->client_id,
                'client_name' => $payment->client->user->name,
                'amount' => $payment->amount,
                'reported_date' => $payment->reported_date?->toDateString(),
                'transfer_title' => $payment->transfer_title,
            ]);

        // Abonamenty otwarte, ktorych okres obejmuje biezacy miesiac. Wg sekcji 8a
        // limit 20/mies. NIE jest egzekwowany — to tylko licznik informacyjny.
        $openMembershipsThisMonth = Membership::query()
            ->whereHas('membershipType', fn ($query) => $query->where('mode', MembershipMode::Open))
            ->where(fn ($query) => $query->whereNull('start_date')->orWhere('start_date', '<=', $monthEnd->toDateString()))
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $monthStart->toDateString()))
            ->count();

        return [
            'endingMemberships' => $endingMemberships,
            'pendingPayments' => $pendingPayments,
            'pendingPaymentsTotal' => round((float) $pendingPayments->sum('amount'), 2),
            'openMembershipsThisMonth' => $openMembershipsThisMonth,
            'openMembershipsLimit' => 20,
            'clientsActive' => Client::where('status', ClientStatus::Active)->count(),
            'clientsTotal' => Client::count(),
        ];
    }
}
