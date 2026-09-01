<?php

namespace App\Http\Controllers\Client;

use App\Domain\Memberships\Enums\MembershipMode;
use App\Domain\Memberships\Enums\ValidityPeriodType;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Scheduling\Enums\Weekday;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Http\Controllers\Concerns\ResolvesMonth;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EnrollmentController extends Controller
{
    use ResolvesMonth;

    /**
     * Strona wyboru zajec cyklicznych na biezacy albo nastepny miesiac kalendarzowy,
     * z kalkulacja ceny na zywo (wg liczby wybranych zajec/tydzien). Bez zapisu do
     * bazy i bez obslugi nieobecnosci — to kolejne kroki (10b).
     */
    public function create(Request $request): Response
    {
        $thisMonth = CarbonImmutable::today()->startOfMonth();
        $nextMonth = $thisMonth->addMonthNoOverflow();

        // klient moze wybrac wylacznie biezacy albo nastepny miesiac
        $month = $this->resolveMonth($request->query('month'))->equalTo($nextMonth)
            ? $nextMonth
            : $thisMonth;

        $classGroups = ClassGroup::query()
            ->activeForMonth($month)
            ->with('classType:id,name,color')
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->map(fn (ClassGroup $group): array => [
                'id' => $group->id,
                'weekday' => $group->weekday->value,
                'start_time' => $group->startsAt(),
                'end_time' => $group->endsAt(),
                'type_name' => $group->classType->name,
                'type_color' => $group->classType->color,
                'capacity' => $group->capacity,
                // rezerwacji nie ma jeszcze w systemie -> wszystkie miejsca wolne
                'free_spots' => $group->capacity,
            ]);

        $pricing = MembershipType::query()
            ->where('mode', MembershipMode::Closed)
            ->where('validity_period_type', ValidityPeriodType::CalendarMonth)
            ->whereNotNull('sessions_per_week')
            ->orderBy('sessions_per_week')
            ->get(['name', 'sessions_per_week', 'price'])
            ->map(fn (MembershipType $type): array => [
                'sessions_per_week' => $type->sessions_per_week,
                'name' => $type->name,
                'price' => $type->price,
            ]);

        return Inertia::render('Client/Enroll', [
            'month' => [
                'value' => $month->format('Y-m'),
                'label' => $month->translatedFormat('F Y'),
            ],
            'monthOptions' => [
                ['value' => $thisMonth->format('Y-m'), 'label' => $thisMonth->translatedFormat('F Y')],
                ['value' => $nextMonth->format('Y-m'), 'label' => $nextMonth->translatedFormat('F Y')],
            ],
            'weekdays' => Weekday::options(),
            'classGroups' => $classGroups,
            'pricing' => $pricing->values(),
        ]);
    }
}
