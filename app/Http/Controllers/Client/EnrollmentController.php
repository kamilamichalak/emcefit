<?php

namespace App\Http\Controllers\Client;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Actions\AssignMembership;
use App\Domain\Memberships\Actions\ResolveClosedMembershipVariant;
use App\Domain\Memberships\Data\MembershipData;
use App\Domain\Memberships\Enums\MembershipMode;
use App\Domain\Memberships\Enums\ValidityPeriodType;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Reservations\Actions\SubmitEnrollment;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\FreeSpots;
use App\Domain\Reservations\SeatHolds;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Enums\Weekday;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Http\Controllers\Concerns\ResolvesMonth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SubmitEnrollmentRequest;
use App\Mail\EnrollmentSubmittedMail;
use App\Support\ClientMailer;
use App\Support\PaymentDeadline;
use App\Support\PolishMonth;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class EnrollmentController extends Controller
{
    use ResolvesMonth;

    public function create(Request $request, FreeSpots $freeSpots, ?Client $client = null): Response
    {
        $month = $this->targetMonth($request->query('month'));
        $client = $this->targetClient($request);

        $alreadyEnrolled = $client
            ?->memberships()
            ->whereDate('start_date', $month->startOfMonth()->toDateString())
            ->exists() ?? false;

        $classGroups = ClassGroup::query()
            ->activeForMonth($month)
            ->with('classType:id,name,color,icon')
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get();

        $today = CarbonImmutable::today();

        $occurrences = ClassSchedule::query()
            ->whereIn('class_group_id', $classGroups->pluck('id'))
            ->whereBetween('date', $this->monthBounds($month))
            ->orderBy('date')
            ->get();

        $availability = $this->availability($classGroups, $occurrences, $month, $client?->id);

        $occurrencesByGroup = $occurrences
            ->groupBy('class_group_id')
            ->map(fn ($rows) => $rows->map(fn (ClassSchedule $occurrence): array => [
                'id' => $occurrence->id,
                'date' => $occurrence->date->toDateString(),
                'label' => $occurrence->date->translatedFormat('D j.m'),
                'cancelled' => $occurrence->status === ClassOccurrenceStatus::Cancelled,
                'cancellation_reason' => $occurrence->cancellation_reason,
                // Prompt 10h: minione wystąpienia nie są już do wyboru
                'past' => $occurrence->date->lt($today),
            ])->values());

        return Inertia::render('Client/Enroll', [
            'month' => ['value' => $month->format('Y-m'), 'label' => $month->translatedFormat('F Y')],
            'monthOptions' => $this->monthOptions(),
            'weekdays' => Weekday::options(),
            'classGroups' => $classGroups->map(fn (ClassGroup $group): array => [
                'id' => $group->id,
                'weekday' => $group->weekday->value,
                'start_time' => $group->startsAt(),
                'end_time' => $group->endsAt(),
                'type_name' => $group->classType->name,
                'type_color' => $group->classType->color,
                'type_icon' => $group->classType->icon,
                'capacity' => $group->capacity,
                'free_spots' => $availability[$group->id]['free'],
                'pending_payment' => $availability[$group->id]['pending'],
            ])->values(),
            'occurrencesByGroup' => $occurrencesByGroup,
            'scheduleGenerated' => $occurrencesByGroup->isNotEmpty(),
            'enrollmentOpen' => EnrollmentWindow::isOpenFor($month),
            'alreadyEnrolled' => $alreadyEnrolled,
            // skrót do „Wolnych miejsc”, gdy klientka ma zgłoszenie, a coś do odrobienia/wykorzystania
            'openOffer' => $this->openOffer($month),
            'makeupCredits' => $client && $alreadyEnrolled ? $freeSpots->makeupCreditsCount($client) : 0,
            'openRemaining' => $client && $alreadyEnrolled ? ($freeSpots->openEntitlement($client)['remaining'] ?? 0) : 0,
            'pricing' => $this->pricing(),
            'previous' => $client ? $this->previousPlan($client, $month, $classGroups) : null,
            'ctx' => $this->context($request),
        ]);
    }

    /**
     * „Zapisz mnie jak w poprzednim miesiącu”: zajęcia z karnetu z poprzedniego miesiąca
     * dopasowane do grafiku tego miesiąca (ten sam dzień, godzina i rodzaj zajęć).
     *
     * @param  Collection<int, ClassGroup>  $classGroups  grafik miesiąca docelowego
     * @return array{month_label: string, group_ids: list<int>, items: list<string>, missing: list<string>}|null
     */
    private function previousPlan(Client $client, CarbonImmutable $month, Collection $classGroups): ?array
    {
        $previous = $client->memberships()
            ->whereDate('start_date', $month->subMonthNoOverflow()->startOfMonth()->toDateString())
            ->with('classGroups.classType:id,name')
            ->first();

        if ($previous === null || $previous->classGroups->isEmpty()) {
            return null;
        }

        $label = fn (ClassGroup $g): string => $g->weekday->label().' '.$g->startsAt().' — '.$g->classType->name;
        $groupIds = [];
        $items = [];
        $missing = [];

        foreach ($previous->classGroups->sortBy(fn (ClassGroup $g) => sprintf('%d %s', $g->weekday->value, $g->start_time)) as $old) {
            $match = $classGroups->first(fn (ClassGroup $new): bool => $new->weekday === $old->weekday
                && $new->startsAt() === $old->startsAt()
                && $new->class_type_id === $old->class_type_id);

            if ($match) {
                $groupIds[] = $match->id;
                $items[] = $label($match);
            } else {
                $missing[] = $label($old);
            }
        }

        return [
            // dopełniacz („z listopada”) — Carbon odmienia nazwę miesiąca tylko przy dniu
            'month_label' => trim(str_replace('1', '', $previous->start_date->copy()->day(1)->translatedFormat('j F'))),
            'group_ids' => $groupIds,
            'items' => $items,
            'missing' => $missing,
        ];
    }

    public function store(SubmitEnrollmentRequest $request, ResolveClosedMembershipVariant $resolveVariant, SubmitEnrollment $submitEnrollment, ?Client $client = null): RedirectResponse
    {
        $month = $this->targetMonth($request->input('month'));
        $groupIds = $request->classGroupIds();
        $client = $this->targetClient($request);
        $onBehalf = $this->actingForClient($request);

        if (! EnrollmentWindow::isOpenFor($month)) {
            return back()->withErrors(['class_group_ids' => 'Zapisy na ten miesiąc nie są otwarte.']);
        }

        $activeGroups = ClassGroup::query()->activeForMonth($month)
            ->whereIn('id', $groupIds)
            ->with('classType:id,name')
            ->get();

        if ($activeGroups->count() !== count($groupIds)) {
            return back()->withErrors(['class_group_ids' => 'Wybrane zajęcia nie są dostępne w tym miesiącu.']);
        }

        // Prompt 23: klient nie zapisze się na zajęcia bez wolnych miejsc (zapłaciłby za
        // miejsce, którego klub nie może dać). Admin w imieniu klienta — świadomy wyjątek.
        if (! $onBehalf) {
            $availability = $this->availability(
                $activeGroups,
                ClassSchedule::query()
                    ->whereIn('class_group_id', $groupIds)
                    ->whereBetween('date', $this->monthBounds($month))
                    ->get(),
                $month,
                $client->id,
            );

            $full = $activeGroups->filter(fn (ClassGroup $group): bool => $availability[$group->id]['free'] <= 0);

            if ($full->isNotEmpty()) {
                $labels = $full->map(fn (ClassGroup $group): string => "{$group->classType->name} ({$group->weekday->label()} {$group->startsAt()})");

                return back()->withErrors([
                    'class_group_ids' => 'Brak wolnych miejsc: '.$labels->implode(', ').'. Odznacz te zajęcia, żeby wysłać zgłoszenie.',
                ]);
            }
        }

        if ($client->memberships()->whereDate('start_date', $month->startOfMonth()->toDateString())->exists()) {
            return back()->withErrors(['class_group_ids' => ($onBehalf ? 'Ten klient ma już' : 'Masz już').' zgłoszenie na ten miesiąc.']);
        }

        $absences = ClassSchedule::query()
            ->whereIn('id', $request->absences())
            ->whereIn('class_group_id', $groupIds)
            ->whereBetween('date', $this->monthBounds($month))
            ->pluck('id')
            ->all();

        // Prompt 10e: pełny miesiąc → wariant miesięczny; pominięte całe tygodnie →
        // krótszy pakiet "N tygodni od pierwszego wejścia" (o ile jest w cenniku).
        $variant = $resolveVariant->handle($groupIds, $month, $absences);

        if ($variant->type === null) {
            return back()->withErrors([
                'class_group_ids' => 'Cennik nie przewiduje wariantu na '.count($groupIds).' zajęć w tygodniu.',
            ]);
        }

        $membership = $submitEnrollment->handle(
            $client,
            $variant->type,
            $month,
            $groupIds,
            $absences,
            $variant->firstEntryDate,
            $variant->endDate,
            $onBehalf ? $request->user()->id : null,
        );

        ClientMailer::send($client, new EnrollmentSubmittedMail($membership), 'enrollment_submitted');

        if ($onBehalf) {
            return redirect()->route('admin.clients.show', $client)
                ->with('success', "Zapisano klienta {$client->user->name} na {$month->translatedFormat('F Y')} — {$variant->type->name}.");
        }

        return redirect()
            ->route('client.enrollment.confirmation', $membership)
            ->with('success', 'Zgłoszenie przyjęte.');
    }

    /**
     * Klientka sama wybiera karnet otwarty na miesiąc (link na dole strony zapisów).
     */
    public function storeOpen(Request $request, AssignMembership $assign): RedirectResponse
    {
        $month = $this->targetMonth($request->input('month'));
        $client = $request->user()->client;
        abort_if($client === null, 403);

        if (! EnrollmentWindow::isOpenFor($month)) {
            return back()->withErrors(['class_group_ids' => 'Zapisy na ten miesiąc nie są otwarte.']);
        }

        if ($client->memberships()->whereDate('start_date', $month->startOfMonth()->toDateString())->exists()) {
            return back()->withErrors(['class_group_ids' => 'Masz już zgłoszenie na ten miesiąc.']);
        }

        $offer = $this->openOffer($month);

        if ($offer === null) {
            return back()->withErrors(['class_group_ids' => 'Karnet otwarty jest teraz niedostępny.']);
        }

        if (! $offer['available']) {
            return back()->withErrors(['class_group_ids' => 'Brak wolnych karnetów otwartych na ten miesiąc — napisz do studia.']);
        }

        $membership = $assign->handle($client, new MembershipData($offer['type_id'], $month->startOfMonth()->toDateString(), null, null, null));

        ClientMailer::send($client, new EnrollmentSubmittedMail($membership), 'enrollment_submitted');

        return redirect()->route('client.enrollment.confirmation', $membership)
            ->with('success', 'Karnet otwarty wybrany. Zajęcia wybierasz w „Wolnych miejscach”.');
    }

    /**
     * Karnet otwarty do wyboru na stronie zapisów: cena i czy jest jeszcze w limicie.
     *
     * @return array{type_id: int, name: string, price: string, entries: int, weeks: int|null, available: bool, deadline: string}|null
     */
    private function openOffer(CarbonImmutable $month): ?array
    {
        $type = MembershipType::query()
            ->where('mode', MembershipMode::Open)
            ->orderBy('entry_count')
            ->first();

        if ($type === null) {
            return null;
        }

        $taken = Membership::query()
            ->whereDate('start_date', $month->startOfMonth()->toDateString())
            ->whereHas('membershipType', fn ($q) => $q->where('mode', MembershipMode::Open))
            ->count();

        return [
            'type_id' => $type->id,
            'name' => $type->name,
            'price' => (string) $type->price,
            'entries' => (int) $type->entry_count,
            'weeks' => $type->validity_period_value ? (int) $type->validity_period_value : null,
            'available' => $taken < config('club.open_membership_limit'),
            'deadline' => PaymentDeadline::forMonth($month)->translatedFormat('j F'),
        ];
    }

    public function confirmation(Request $request, Membership $membership): Response
    {
        abort_unless($membership->client_id === $request->user()->client?->id, 403);

        $membership->load(['membershipType', 'classGroups.classType', 'reservations']);

        return Inertia::render('Client/EnrollmentConfirmation', [
            'monthLabel' => $membership->start_date->translatedFormat('F Y'),
            'paymentDeadline' => PaymentDeadline::label($membership),
            'monthIn' => PolishMonth::in($membership->start_date),
            'membershipTypeName' => $membership->membershipType->name,
            'isOpen' => $membership->membershipType->mode === MembershipMode::Open,
            'price' => $membership->price_locked,
            'classes' => $membership->classGroups
                // Prompt 16d: kolejność dni tygodnia od poniedziałku
                ->sortBy(fn (ClassGroup $group): string => sprintf('%d %s', $group->weekday->value, $group->start_time))
                ->map(fn (ClassGroup $group): array => [
                    'weekday_label' => $group->weekday->label(),
                    'start_time' => $group->startsAt(),
                    'end_time' => $group->endsAt(),
                    'type_name' => $group->classType->name,
                    'type_color' => $group->classType->color,
                    'type_icon' => $group->classType->icon,
                ])->values(),
            'pendingCount' => $membership->reservations
                ->where('status', ReservationStatus::PendingPayment)->count(),
            'makeupCount' => $membership->reservations
                ->where('status', ReservationStatus::Released)->count(),
            'bank' => [
                'account' => config('club.bank_account'),
                'title' => 'zajęcia fitness, '.$request->user()->name.', '.$membership->start_date->translatedFormat('F Y'),
            ],
        ]);
    }

    /**
     * Wolne miejsca i liczba osób czekających na zaksięgowanie wpłaty per class_group.
     *
     * Wolne = minimum z (limit − potwierdzone) po wystąpieniach, które klient może jeszcze
     * wybrać — najciaśniejszy termin decyduje o całym miesiącu (Prompt 10i), ale minione
     * i odwołane przez klub terminy nie blokują zapisu (Prompt 23). Nieopłacone zgłoszenia
     * nie zajmują miejsca (regulamin pkt 35–36) — liczymy je tylko informacyjnie.
     *
     * @param  Collection<int, ClassGroup>  $classGroups
     * @param  Collection<int, ClassSchedule>  $occurrences
     * @return Collection<int, array{free: int, pending: int}>
     */
    private function availability(Collection $classGroups, Collection $occurrences, CarbonImmutable $month, ?int $clientId = null): Collection
    {
        $today = CarbonImmutable::today();
        $holds = app(SeatHolds::class);

        $open = $occurrences->filter(fn (ClassSchedule $occurrence): bool => $occurrence->status !== ClassOccurrenceStatus::Cancelled
            && ! $occurrence->date->lt($today));

        $reservations = $open->isEmpty()
            ? collect()
            : Reservation::query()
                ->whereIn('class_schedule_id', $open->pluck('id'))
                ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::PendingPayment])
                ->get(['class_schedule_id', 'client_id', 'status']);

        $confirmedByOccurrence = $reservations
            ->where('status', ReservationStatus::Confirmed)
            ->countBy('class_schedule_id');

        return $classGroups->mapWithKeys(function (ClassGroup $group) use ($open, $reservations, $confirmedByOccurrence, $holds, $month, $clientId): array {
            $groupOccurrenceIds = $open->where('class_group_id', $group->id)->pluck('id');

            $free = $groupOccurrenceIds->isEmpty()
                ? $group->capacity
                : $groupOccurrenceIds->map(fn (int $id): int => $group->capacity - ($confirmedByOccurrence[$id] ?? 0))->min();

            // miejsca trzymane dla stałych uczestniczek z poprzedniego miesiąca (do 1. dnia miesiąca)
            $free = max(0, $free - $holds->count($group, $month, $clientId, includeUnpaid: true));

            $pending = $reservations
                ->where('status', ReservationStatus::PendingPayment)
                ->whereIn('class_schedule_id', $groupOccurrenceIds)
                ->pluck('client_id')
                ->unique()
                ->count();

            return [$group->id => ['free' => $free, 'pending' => $pending]];
        });
    }

    /**
     * Klient, którego dotyczy zapis: z parametru trasy (admin działa w jego imieniu,
     * Prompt 17) albo zalogowany klient (samozapis). Jeden mechanizm dla obu ścieżek.
     */
    private function targetClient(Request $request): ?Client
    {
        $routeClient = $request->route('client');

        return $routeClient instanceof Client ? $routeClient : $request->user()->client;
    }

    private function actingForClient(Request $request): bool
    {
        return $request->route('client') instanceof Client;
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Request $request): array
    {
        $client = $this->targetClient($request);
        $onBehalf = $this->actingForClient($request);

        return [
            'admin_mode' => $onBehalf,
            'client_name' => $onBehalf ? $client->user->name : null,
            'create_route' => $onBehalf ? 'admin.clients.enrollment.create' : 'client.enrollment.create',
            'store_route' => $onBehalf ? 'admin.clients.enrollment.store' : 'client.enrollment.store',
            'submission_route' => $onBehalf ? 'admin.clients.show' : 'client.classes.index',
            'route_params' => $onBehalf ? ['client' => $client->id] : [],
        ];
    }

    private function targetMonth(mixed $input): CarbonImmutable
    {
        $thisMonth = CarbonImmutable::today()->startOfMonth();
        $nextMonth = $thisMonth->addMonthNoOverflow();

        return $this->resolveMonth($input)->equalTo($nextMonth) ? $nextMonth : $thisMonth;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function monthBounds(CarbonImmutable $month): array
    {
        return [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()];
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

    /**
     * Warianty karnetu zamkniętego dla kalkulacji ceny na żywo: miesięczne oraz
     * krótsze "N tygodni od pierwszego wejścia" (Prompt 10e). Front sam wybiera,
     * który pasuje do wzorca obecności.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function pricing()
    {
        return MembershipType::query()
            ->where('mode', MembershipMode::Closed)
            ->whereIn('validity_period_type', [
                ValidityPeriodType::CalendarMonth,
                ValidityPeriodType::WeeksFromFirstEntry,
            ])
            ->whereNotNull('sessions_per_week')
            ->orderBy('sessions_per_week')
            ->orderBy('validity_period_value')
            ->get(['name', 'sessions_per_week', 'price', 'validity_period_type', 'validity_period_value'])
            ->map(fn (MembershipType $type): array => [
                'sessions_per_week' => $type->sessions_per_week,
                'name' => $type->name,
                'price' => $type->price,
                'validity_type' => $type->validity_period_type->value,
                'validity_value' => $type->validity_period_value,
            ])
            ->values();
    }
}
