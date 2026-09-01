<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Scheduling\Actions\CancelClassOccurrence;
use App\Domain\Scheduling\Actions\GenerateMonthlySchedule;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Http\Controllers\Concerns\ResolvesMonth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelClassOccurrenceRequest;
use App\Http\Requests\Admin\GenerateMonthlyScheduleRequest;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->with(['classGroup.classType:id,name,color', 'classGroup.trainer.user:id,name'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(function (ClassSchedule $occurrence): array {
                $start = Carbon::parse($occurrence->start_time);

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
                ];
            });

        return Inertia::render('Admin/Schedule/Index', [
            'month' => $this->presentMonth($month),
            'occurrences' => $occurrences,
            'generated' => $occurrences->isNotEmpty(),
            'patternCount' => ClassGroup::query()->activeForMonth($month)->count(),
        ]);
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
