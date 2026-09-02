<?php

namespace App\Http\Controllers\Client;

use App\Domain\Memberships\Actions\ResolveClosedMembershipVariant;
use App\Domain\Memberships\Enums\MembershipMode;
use App\Domain\Memberships\Enums\ValidityPeriodType;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Reservations\Actions\SubmitEnrollment;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Enums\Weekday;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Http\Controllers\Concerns\ResolvesMonth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SubmitEnrollmentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class EnrollmentController extends Controller
{
    use ResolvesMonth;

    public function create(Request $request): Response
    {
        $month = $this->targetMonth($request->query('month'));

        $alreadyEnrolled = $request->user()->client
            ?->memberships()
            ->whereDate('start_date', $month->startOfMonth()->toDateString())
            ->exists() ?? false;

        $classGroups = ClassGroup::query()
            ->activeForMonth($month)
            ->with('classType:id,name,color')
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get();

        $occurrencesByGroup = ClassSchedule::query()
            ->whereIn('class_group_id', $classGroups->pluck('id'))
            ->whereBetween('date', $this->monthBounds($month))
            ->orderBy('date')
            ->get()
            ->groupBy('class_group_id')
            ->map(fn ($rows) => $rows->map(fn (ClassSchedule $occurrence): array => [
                'id' => $occurrence->id,
                'date' => $occurrence->date->toDateString(),
                'label' => $occurrence->date->translatedFormat('D j.m'),
                'cancelled' => $occurrence->status === ClassOccurrenceStatus::Cancelled,
                'cancellation_reason' => $occurrence->cancellation_reason,
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
                'capacity' => $group->capacity,
                'free_spots' => $group->capacity,
            ])->values(),
            'occurrencesByGroup' => $occurrencesByGroup,
            'scheduleGenerated' => $occurrencesByGroup->isNotEmpty(),
            'enrollmentOpen' => EnrollmentWindow::isOpenFor($month),
            'alreadyEnrolled' => $alreadyEnrolled,
            'pricing' => $this->pricing(),
        ]);
    }

    public function store(SubmitEnrollmentRequest $request, ResolveClosedMembershipVariant $resolveVariant, SubmitEnrollment $submitEnrollment): RedirectResponse
    {
        $month = $this->targetMonth($request->input('month'));
        $groupIds = $request->classGroupIds();
        $client = $request->user()->client;

        if (! EnrollmentWindow::isOpenFor($month)) {
            return back()->withErrors(['class_group_ids' => 'Zapisy na ten miesiąc nie są otwarte.']);
        }

        $activeIds = ClassGroup::query()->activeForMonth($month)
            ->whereIn('id', $groupIds)->pluck('id');

        if ($activeIds->count() !== count($groupIds)) {
            return back()->withErrors(['class_group_ids' => 'Wybrane zajęcia nie są dostępne w tym miesiącu.']);
        }

        if ($client->memberships()->whereDate('start_date', $month->startOfMonth()->toDateString())->exists()) {
            return back()->withErrors(['class_group_ids' => 'Masz już zgłoszenie na ten miesiąc.']);
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
        );

        return redirect()
            ->route('client.enrollment.confirmation', $membership)
            ->with('success', 'Zgłoszenie przyjęte.');
    }

    public function confirmation(Request $request, Membership $membership): Response
    {
        abort_unless($membership->client_id === $request->user()->client?->id, 403);

        $membership->load(['membershipType', 'classGroups.classType', 'reservations']);

        return Inertia::render('Client/EnrollmentConfirmation', [
            'monthLabel' => $membership->start_date->translatedFormat('F Y'),
            'membershipTypeName' => $membership->membershipType->name,
            'price' => $membership->membershipType->price,
            'classes' => $membership->classGroups
                ->sortBy([['weekday', 'asc'], ['start_time', 'asc']])
                ->map(fn (ClassGroup $group): array => [
                    'weekday_label' => $group->weekday->label(),
                    'start_time' => $group->startsAt(),
                    'end_time' => $group->endsAt(),
                    'type_name' => $group->classType->name,
                    'type_color' => $group->classType->color,
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
