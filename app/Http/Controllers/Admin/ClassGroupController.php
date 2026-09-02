<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Scheduling\Actions\AddClassToPattern;
use App\Domain\Scheduling\Actions\CopyPatternIntoMonth;
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
        $monthKey = $month->format('Y-m');

        $groups = ClassGroup::query()
            ->activeForMonth($month)
            ->with(['classType:id,name,color,icon', 'trainer.user:id,name'])
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->map(fn (ClassGroup $group): array => $this->presentGroup($group, $monthKey));

        // Miesiac "dziedziczy" wzorzec, gdy pokazuje jakies zajecia, ale zaden wiersz
        // nie jest zakotwiczony w tym miesiacu (active_from z wczesniejszego miesiaca).
        $ownGroups = $groups->reject(fn (array $g): bool => $g['inherited']);
        $isInherited = $groups->isNotEmpty() && $ownGroups->isEmpty();

        $nextMonth = $month->addMonthNoOverflow()->startOfMonth();

        return Inertia::render('Admin/ClassGroups/Index', [
            'month' => $this->presentMonth($month),
            'weekdays' => Weekday::options(),
            'groups' => $groups->values(),
            'patternInherited' => $isInherited,
            'inheritedFromLabel' => $isInherited ? $groups->first()['anchor_label'] : null,
            'nextMonthHasOwnPattern' => ClassGroup::query()
                ->whereYear('active_from', $nextMonth->year)
                ->whereMonth('active_from', $nextMonth->month)
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
        $classGroup->load(['classType:id,name,color,icon', 'trainer.user:id,name']);

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

    public function copyPattern(CopyPatternRequest $request, CopyPatternIntoMonth $copyPatternIntoMonth): RedirectResponse
    {
        $targetMonth = $this->resolveMonth($request->input('month'));
        $result = $copyPatternIntoMonth->handle($targetMonth, $request->boolean('force'));

        $targetLabel = CarbonImmutable::parse($result->targetMonth.'-01')->translatedFormat('F Y');

        return match ($result->status) {
            'empty' => back()->with('error', 'Brak wzorca do skopiowania na ten miesiąc.'),
            'conflict' => back()->with('warning', "{$targetLabel} ma już własny wzorzec ({$result->count} zajęć). Skopiuj z nadpisaniem, jeśli chcesz go zastąpić."),
            default => redirect()
                ->route('admin.class-groups.index', ['month' => $result->targetMonth])
                ->with('success', "Wzorzec skopiowany na {$targetLabel} ({$result->count} zajęć) — możesz go teraz edytować niezależnie."),
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
    private function presentGroup(ClassGroup $group, string $viewedMonthKey): array
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
            'type_icon' => $group->classType->icon,
            'trainer_name' => $group->trainer?->user?->name,
            // wiersz dziedziczony = zakotwiczony w miesiacu wczesniejszym niz ogladany
            'inherited' => $group->active_from->format('Y-m') !== $viewedMonthKey,
            'anchor_label' => $group->active_from->translatedFormat('F Y'),
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
