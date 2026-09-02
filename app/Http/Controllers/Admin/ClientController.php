<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Clients\Actions\CreateClient;
use App\Domain\Clients\Actions\SetClientStatus;
use App\Domain\Clients\Actions\UpdateClient;
use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClientRequest;
use App\Http\Requests\Admin\UpdateClientRequest;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();

        $clients = Client::query()
            ->with('user:id,name,email')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('user', function (Builder $query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(
                ClientStatus::tryFrom($status),
                fn (Builder $query, ClientStatus $status) => $query->where('status', $status),
            )
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->user->name,
                'email' => $client->user->email,
                'phone' => $client->phone,
                'status' => $client->status->value,
                'status_label' => $client->status->label(),
                'login_configured' => $client->isActivated(),
                'join_date' => $client->join_date?->toDateString(),
            ]);

        return Inertia::render('Admin/Clients/Index', [
            'clients' => $clients,
            'filters' => ['search' => $search, 'status' => $status],
            'statuses' => ClientStatus::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Clients/Create');
    }

    public function show(Client $client): Response
    {
        $client->load([
            'user:id,name,email',
            'memberships' => fn ($query) => $query->latest('id')->with([
                'membershipType:id,name,mode,sessions_per_week',
                'modifiedBy:id,name',
                'classGroups.classType:id,name,color,icon',
                'payments' => fn ($query) => $query->latest('reported_date')->latest('id'),
            ]),
            'reservations.classSchedule.classGroup.classType:id,name,color,icon',
            'makeupCredits.sourceReservation.classSchedule.classGroup.classType:id,name',
        ]);

        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth();

        $activeMembership = $client->memberships->first(
            fn (Membership $membership): bool => $membership->isPaid()
                && ($membership->end_date === null || $membership->end_date->gte($today)),
        );

        $allPayments = $client->memberships
            ->flatMap(fn (Membership $membership) => $membership->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'membership_id' => $membership->id,
                'membership_type_name' => $membership->membershipType->name,
                'amount' => $payment->amount,
                'reported_date' => $payment->reported_date?->toDateString(),
                'settled_date' => $payment->settled_date?->toDateString(),
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'transfer_title' => $payment->transfer_title,
                '_sort' => [$payment->reported_date?->timestamp ?? 0, $payment->id],
            ]))
            ->sortByDesc('_sort')
            ->map(fn (array $row): array => Arr::except($row, '_sort'))
            ->values();

        $settledTotal = $client->memberships
            ->flatMap->payments
            ->where('status', PaymentStatus::Settled)
            ->sum('amount');

        $pending = $client->memberships
            ->flatMap->payments
            ->where('status', PaymentStatus::Pending);

        $reservationRow = fn ($reservation): array => [
            'id' => $reservation->id,
            'date' => $reservation->classSchedule->date->translatedFormat('D, j F Y'),
            'start_time' => $reservation->classSchedule->startsAt(),
            'type_name' => $reservation->classSchedule->classGroup->classType->name,
            'type_color' => $reservation->classSchedule->classGroup->classType->color,
            'type_icon' => $reservation->classSchedule->classGroup->classType->icon,
            'status' => $reservation->status->value,
            'status_label' => $reservation->status->label(),
            'reported_at' => $reservation->reported_at?->toDateString(),
            'confirmed_at' => $reservation->confirmed_at?->toDateString(),
        ];

        $reservationsByMembership = $client->reservations
            ->sortByDesc(fn ($reservation) => $reservation->classSchedule->date->toDateString().$reservation->classSchedule->start_time)
            ->groupBy('membership_id');

        // Zakładki miesięcy (Prompt 16b): jedna na każdy karnet klienta, od najnowszego.
        // Miesiące archiwalne są tu wyłącznie do odczytu.
        $monthTabs = $client->memberships
            ->map(function (Membership $membership) use ($reservationsByMembership, $reservationRow): array {
                $anchor = CarbonImmutable::parse(
                    $membership->start_date?->toDateString() ?? $membership->created_at->toDateString()
                );

                return [
                    'membership_id' => $membership->id,
                    'value' => $anchor->format('Y-m'),
                    'label' => $anchor->translatedFormat('F Y'),
                    'sort_key' => $anchor->format('Y-m-d').'-'.str_pad((string) $membership->id, 8, '0', STR_PAD_LEFT),
                    'type_name' => $membership->membershipType->name,
                    'price' => $membership->price_locked,
                    'start_date' => $membership->start_date?->toDateString(),
                    'end_date' => $membership->end_date?->toDateString(),
                    'payment_status' => $membership->isPaid()
                        ? 'zaksiegowana'
                        : ($membership->hasPendingPayment() ? 'oczekuje' : 'brak'),
                    'payment_status_label' => $membership->isPaid()
                        ? 'Zaksięgowana'
                        : ($membership->hasPendingPayment() ? 'Oczekuje na zaksięgowanie' : 'Brak zarejestrowanej płatności'),
                    'classes' => $membership->classGroups
                        // Prompt 16d: kolejność dni tygodnia od poniedziałku
                        ->sortBy(fn ($group): string => sprintf('%d %s', $group->weekday->value, $group->start_time))
                        ->map(fn ($group): array => [
                            'weekday_label' => $group->weekday->label(),
                            'start_time' => $group->startsAt(),
                            'end_time' => $group->endsAt(),
                            'type_name' => $group->classType->name,
                            'type_color' => $group->classType->color,
                            'type_icon' => $group->classType->icon,
                        ])->values(),
                    'reservations' => ($reservationsByMembership->get($membership->id) ?? collect())
                        ->map($reservationRow)->values(),
                ];
            })
            ->sortByDesc('sort_key')
            ->map(fn (array $tab): array => Arr::except($tab, 'sort_key'))
            ->values();

        $makeupCredits = $client->makeupCredits
            ->sortByDesc(fn ($credit) => [$credit->created_at?->timestamp ?? 0, $credit->id])
            ->map(function ($credit) use ($monthStart): array {
                $expired = ! $credit->used
                    && $credit->expires_end_of_month
                    && $credit->created_at !== null
                    && $credit->created_at->lt($monthStart);

                $source = $credit->sourceReservation?->classSchedule;

                return [
                    'id' => $credit->id,
                    'source_date' => $source?->date->translatedFormat('D, j F Y'),
                    'source_type_name' => $credit->sourceReservation?->classSchedule?->classGroup?->classType?->name,
                    'granted_at' => $credit->created_at?->toDateString(),
                    'expires_end_of_month' => $credit->expires_end_of_month,
                    'state' => $credit->used ? 'used' : ($expired ? 'expired' : 'available'),
                ];
            })
            ->values();

        return Inertia::render('Admin/Clients/Show', [
            'client' => [
                'id' => $client->id,
                'name' => $client->user->name,
                'email' => $client->user->email,
                'phone' => $client->phone,
                'status' => $client->status->value,
                'status_label' => $client->status->label(),
                'join_date' => $client->join_date?->toDateString(),
                'birth_date' => $client->birth_date?->toDateString(),
                'terms_accepted_at' => $client->terms_accepted_at?->toDateTimeString(),
                'health_declaration_at' => $client->health_declaration_at?->toDateTimeString(),
            ],
            'login' => [
                'configured' => $client->isActivated(),
                'configured_at' => $client->invitation_used_at?->toDateTimeString(),
                'activation_link' => $client->isActivated()
                    ? null
                    : URL::temporarySignedRoute('client.activate.show', now()->addDays(7), ['client' => $client->id]),
                // Reset hasła ma sens dopiero, gdy klient ma już skonfigurowane logowanie (Prompt 18)
                'reset_link' => $client->isActivated()
                    ? URL::temporarySignedRoute('client.password-reset.show', now()->addDay(), ['client' => $client->id])
                    : null,
            ],
            'summary' => [
                'memberships_count' => $client->memberships->count(),
                'active_membership' => $activeMembership === null ? null : [
                    'type_name' => $activeMembership->membershipType->name,
                    'end_date' => $activeMembership->end_date?->toDateString(),
                ],
                'settled_total' => round((float) $settledTotal, 2),
                'pending_total' => round((float) $pending->sum('amount'), 2),
                'pending_count' => $pending->count(),
            ],
            'memberships' => $client->memberships->map(fn (Membership $membership): array => [
                'id' => $membership->id,
                'type_name' => $membership->membershipType->name,
                'mode_label' => $membership->membershipType->mode->label(),
                'month_label' => $membership->start_date?->translatedFormat('F Y'),
                'price_locked' => $membership->price_locked,
                'sessions_per_week' => $membership->membershipType->sessions_per_week,
                'class_groups_count' => $membership->classGroups->count(),
                'start_date' => $membership->start_date?->toDateString(),
                'end_date' => $membership->end_date?->toDateString(),
                'first_entry_date' => $membership->first_entry_date?->toDateString(),
                'entries_remaining' => $membership->entries_remaining,
                'is_paid' => $membership->isPaid(),
                'awaiting_payment' => $membership->hasPendingPayment(),
                'payments_count' => $membership->payments->count(),
                'modified' => $membership->modified_by_id === null ? null : [
                    'by' => $membership->modifiedBy?->name,
                    'at' => $membership->modified_at?->toDateTimeString(),
                    'note' => $membership->admin_note,
                ],
            ]),
            'payments' => $allPayments,
            'monthTabs' => $monthTabs,
            'makeupCredits' => $makeupCredits,
        ]);
    }

    public function store(StoreClientRequest $request, CreateClient $createClient): RedirectResponse
    {
        $client = $createClient->handle($request->toData());

        return redirect()->route('admin.clients.show', $client)
            ->with('success', 'Klient został dodany. Wygeneruj link aktywacyjny, aby dać mu dostęp.');
    }

    public function edit(Client $client): Response
    {
        $client->load('user:id,name,email');

        return Inertia::render('Admin/Clients/Edit', [
            'client' => [
                'id' => $client->id,
                'name' => $client->user->name,
                'email' => $client->user->email,
                'phone' => $client->phone,
                'birth_date' => $client->birth_date?->toDateString(),
            ],
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client, UpdateClient $updateClient): RedirectResponse
    {
        $updateClient->handle($client, $request->toData());

        return redirect()->route('admin.clients.show', $client)
            ->with('success', 'Dane klienta zostały zaktualizowane.');
    }

    public function updateStatus(Client $client, SetClientStatus $setClientStatus): RedirectResponse
    {
        $status = $setClientStatus->toggle($client);

        return back()->with('success', "Status klienta: {$status->label()}.");
    }
}
