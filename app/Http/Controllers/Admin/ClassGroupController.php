<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Scheduling\Actions\AddClassToPattern;
use App\Domain\Scheduling\Actions\CopyPatternToNextMonth;
use App\Domain\Scheduling\Actions\UpdateClassGroup;
use App\Domain\Scheduling\Enums\Weekday;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassType;
use App\Domain\Trainers\Models\Trainer;
use App\Http\Controllers\Concerns\ResolvesMonth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CopyPatternRequest;
use App\Http\Requests\Admin\StoreClassGroupRequest;
use App\Http\Requests\Admin\UpdateClassGroupRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ClassGroupController extends Controller
{
    use ResolvesMonth;

    public function index(Request $request): Response
    {
        $month = $this->resolveMonth($request->query('month'));

        $groups = ClassGroup::query()
            ->activeForMonth($month)
            ->with(['classType:id,name,color', 'trainer.user:id,name'])
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->map(fn (ClassGroup $group): array => $this->presentGroup($group));

        $nextMonth = $month->addMonthNoOverflow()->startOfMonth();

        return Inertia::render('Admin/ClassGroups/Index', [
            'month' => $this->presentMonth($month),
            'weekdays' => Weekday::options(),
            'groups' => $groups->values(),
            'nextMonthHasPattern' => ClassGroup::query()
                ->whereDate('active_from', '>=', $nextMonth->toDateString())
                ->exists(),
        ]);
    }

    public function create(Request $request): Response
    {
        $month = $this->resolveMonth($request->query('month'));

        return Inertia::render('Admin/ClassGroups/Create', [
            'month' => $this->presentMonth($month),
            'weekdays' => Weekday::options(),
            'defaultWeekday' => $this->resolveWeekday($request->query('weekday')),
            'classTypes' => $this->classTypeOptions(),
            'trainers' => $this->trainerOptions(),
        ]);
    }

    public function store(StoreClassGroupRequest $request, AddClassToPattern $addClassToPattern): RedirectResponse
    {
        $addClassToPattern->handle($request->month(), $request->toData());

        return redirect()
            ->route('admin.class-groups.index', ['month' => $request->month()->format('Y-m')])
            ->with('success', 'Zajęcia dodane do wzorca.');
    }

    public function edit(ClassGroup $classGroup): Response
    {
        $classGroup->load(['classType:id,name,color', 'trainer.user:id,name']);

        return Inertia::render('Admin/ClassGroups/Edit', [
            'month' => $this->presentMonth($classGroup->active_from->toImmutable()),
            'weekdays' => Weekday::options(),
            'classTypes' => $this->classTypeOptions(),
            'trainers' => $this->trainerOptions(),
            'classGroup' => [
                'id' => $classGroup->id,
                'class_type_id' => $classGroup->class_type_id,
                'trainer_id' => $classGroup->trainer_id,
                'weekday' => $classGroup->weekday->value,
                'start_time' => $classGroup->startsAt(),
                'duration_minutes' => $classGroup->duration_minutes,
                'capacity' => $classGroup->capacity,
            ],
        ]);
    }

    public function update(UpdateClassGroupRequest $request, ClassGroup $classGroup, UpdateClassGroup $updateClassGroup): RedirectResponse
    {
        $updateClassGroup->handle($classGroup, $request->toData());

        return redirect()
            ->route('admin.class-groups.index', ['month' => $classGroup->active_from->format('Y-m')])
            ->with('success', 'Zajęcia zaktualizowane.');
    }

    public function destroy(ClassGroup $classGroup): RedirectResponse
    {
        $month = $classGroup->active_from->format('Y-m');
        $classGroup->delete();

        return redirect()
            ->route('admin.class-groups.index', ['month' => $month])
            ->with('success', 'Zajęcia usunięte z wzorca.');
    }

    public function copyToNextMonth(CopyPatternRequest $request, CopyPatternToNextMonth $copyPatternToNextMonth): RedirectResponse
    {
        $month = $this->resolveMonth($request->input('month'));
        $result = $copyPatternToNextMonth->handle($month, $request->boolean('force'));

        $nextLabel = CarbonImmutable::parse($result->nextMonth.'-01')->translatedFormat('F Y');

        return match ($result->status) {
            'empty' => back()->with('error', 'Wzorzec na ten miesiąc jest pusty — nie ma czego kopiować.'),
            'conflict' => back()->with('warning', "Wzorzec na {$nextLabel} już istnieje ({$result->count} zajęć). Skopiuj z nadpisaniem, jeśli chcesz go zastąpić."),
            default => redirect()
                ->route('admin.class-groups.index', ['month' => $result->nextMonth])
                ->with('success', "Wzorzec skopiowany na {$nextLabel} ({$result->count} zajęć). Dostosuj go przed wygenerowaniem harmonogramu."),
        };
    }

    private function resolveWeekday(mixed $input): int
    {
        $value = is_numeric($input) ? (int) $input : 1;

        return $value >= 1 && $value <= 5 ? $value : 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentGroup(ClassGroup $group): array
    {
        return [
            'id' => $group->id,
            'weekday' => $group->weekday->value,
            'start_time' => $group->startsAt(),
            'end_time' => $group->endsAt(),
            'duration_minutes' => $group->duration_minutes,
            'capacity' => $group->capacity,
            'type_name' => $group->classType->name,
            'type_color' => $group->classType->color,
            'trainer_name' => $group->trainer?->user?->name,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function classTypeOptions()
    {
        return ClassType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'default_capacity'])
            ->map(fn (ClassType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'color' => $type->color,
                'default_capacity' => $type->default_capacity,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function trainerOptions()
    {
        return Trainer::query()
            ->with('user:id,name')
            ->get()
            ->map(fn (Trainer $trainer): array => [
                'id' => $trainer->id,
                'name' => $trainer->user?->name ?? 'Trener #'.$trainer->id,
            ]);
    }
}
