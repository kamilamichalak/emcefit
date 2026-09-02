<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reservations\Actions\SetEnrollmentWindow;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Actions\CancelClassOccurrence;
use App\Domain\Scheduling\Actions\GenerateMonthlySchedule;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Http\Controllers\Concerns\ResolvesMonth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelClassOccurrenceRequest;
use App\Http\Requests\Admin\GenerateMonthlyScheduleRequest;
use App\Http\Requests\Admin\SetEnrollmentWindowRequest;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    use ResolvesMonth;

    public function index(Request $request): Response
    {
        $month = $this->resolveMonth($request->query('month'));

        $occurrences = ClassSchedule::query()
            ->whereBetween('date', [
                $month->startOfMonth()->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->with([
                'classGroup.classType:id,name,color',
                'classGroup.trainer.user:id,name',
                'reservations.client.user:id,name',
            ])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(function (ClassSchedule $occurrence): array {
                $start = Carbon::parse($occurrence->start_time);

                $reservations = $this->presentReservations($occurrence->reservations);

                return [
                    'id' => $occurrence->id,
                    'date' => $occurrence->date->toDateString(),
                    'start_time' => $start->format('H:i'),
                    'end_time' => $start->copy()->addMinutes($occurrence->classGroup->duration_minutes)->format('H:i'),
                    'status' => $occurrence->status->value,
                    'type_name' => $occurrence->classGroup->classType->name,
                    'type_color' => $occurrence->classGroup->classType->color,
                    'trainer_name' => $occurrence->classGroup->trainer?->user?->name,
                    'capacity' => $occurrence->classGroup->capacity,
                    'cancellation_reason' => $occurrence->cancellation_reason,
                    'reservations' => $reservations,
                    'confirmed_count' => $reservations->where('status', ReservationStatus::Confirmed->value)->count(),
                    'waitlist_count' => $reservations->where('status', ReservationStatus::Waitlist->value)->count(),
                ];
            });

        return Inertia::render('Admin/Schedule/Index', [
            'month' => $this->presentMonth($month),
            'occurrences' => $occurrences,
            'generated' => $occurrences->isNotEmpty(),
            'patternCount' => ClassGroup::query()->activeForMonth($month)->count(),
            'enrollmentOpen' => EnrollmentWindow::isOpenFor($month),
        ]);
    }

    /**
     * Lista zapisanych na dane wystąpienie: potwierdzeni najpierw, potem lista
     * oczekujących (obie grupy wg daty potwierdzenia rosnąco — kto pierwszy zapłacił),
     * na końcu wciąż oczekujący na płatność. Spec sekcja 16 / Prompt 13a.
     *
     * @param  Collection<int, Reservation>  $reservations
     * @return Collection<int, array<string, mixed>>
     */
    private function presentReservations($reservations)
    {
        $rank = [
            ReservationStatus::Confirmed->value => 0,
            ReservationStatus::Waitlist->value => 1,
            ReservationStatus::PendingPayment->value => 2,
            ReservationStatus::MadeUp->value => 3,
            ReservationStatus::Released->value => 4,
        ];

        return $reservations
            ->sortBy(fn (Reservation $reservation): string => sprintf(
                '%d-%s-%s',
                $rank[$reservation->status->value] ?? 9,
                $reservation->confirmed_at?->format('Y-m-d H:i:s') ?? '9999-99-99',
                $reservation->reported_at?->format('Y-m-d H:i:s') ?? '9999-99-99',
            ))
            ->map(fn (Reservation $reservation): array => [
                'client_name' => $reservation->client->user->name,
                'status' => $reservation->status->value,
                'status_label' => $reservation->status->label(),
                'reported_at' => $reservation->reported_at?->toDateString(),
                'confirmed_at' => $reservation->confirmed_at?->toDateString(),
            ])
            ->values();
    }

    public function setEnrollmentOpen(SetEnrollmentWindowRequest $request, SetEnrollmentWindow $setEnrollmentWindow): RedirectResponse
    {
        $month = $this->resolveMonth($request->input('month'));
        $window = $setEnrollmentWindow->handle($month, $request->boolean('open'));
        $label = $month->translatedFormat('F Y');

        return back()->with('success', $window->open
            ? "Zapisy na {$label} są teraz otwarte dla klientów."
            : "Zapisy na {$label} zostały zamknięte.");
    }

    public function generate(GenerateMonthlyScheduleRequest $request, GenerateMonthlySchedule $generateMonthlySchedule): RedirectResponse
    {
        $month = $this->resolveMonth($request->input('month'));
        $regenerate = $request->boolean('regenerate');

        if (ClassGroup::query()->activeForMonth($month)->doesntExist()) {
            return back()->with('error', 'Brak wzorca tygodniowego dla tego miesiąca — najpierw ułóż grafik.');
        }

        $result = $generateMonthlySchedule->handle($month, $regenerate);

        if ($result->status === 'exists') {
            return back()->with('warning', 'Harmonogram na ten miesiąc już istnieje ('.$result->existing.' zajęć). Użyj „Regeneruj z wzorca", aby zbudować go od nowa.');
        }

        $message = $regenerate
            ? "Harmonogram zregenerowany: {$result->created} nowych zajęć, {$result->removed} usuniętych."
            : "Harmonogram wygenerowany: {$result->created} zajęć.";

        return back()->with('success', $message);
    }

    public function cancelOccurrence(CancelClassOccurrenceRequest $request, ClassSchedule $occurrence, CancelClassOccurrence $cancelClassOccurrence): RedirectResponse
    {
        $cancelClassOccurrence->cancel($occurrence, $request->reason());

        return back()->with('success', 'Zajęcia zostały odwołane.');
    }

    public function restoreOccurrence(ClassSchedule $occurrence, CancelClassOccurrence $cancelClassOccurrence): RedirectResponse
    {
        $cancelClassOccurrence->restore($occurrence);

        return back()->with('success', 'Zajęcia przywrócone jako planowane.');
    }
}
