<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Scheduling\Actions\CreateClassType;
use App\Domain\Scheduling\Actions\UpdateClassType;
use App\Domain\Scheduling\Models\ClassType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClassTypeRequest;
use App\Http\Requests\Admin\UpdateClassTypeRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClassTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/ClassTypes/Index', [
            'classTypes' => ClassType::query()
                ->orderBy('name')
                ->get()
                ->map(fn (ClassType $classType): array => [
                    'id' => $classType->id,
                    'name' => $classType->name,
                    'description' => $classType->description,
                    'required_equipment' => $classType->required_equipment,
                    'color' => $classType->color,
                    'icon' => $classType->icon,
                    'default_capacity' => $classType->default_capacity,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/ClassTypes/Create');
    }

    public function store(StoreClassTypeRequest $request, CreateClassType $createClassType): RedirectResponse
    {
        $createClassType->handle($request->toData());

        return redirect()->route('admin.class-types.index')
            ->with('success', 'Typ zajęć został dodany.');
    }

    public function edit(ClassType $classType): Response
    {
        return Inertia::render('Admin/ClassTypes/Edit', [
            'classType' => [
                'id' => $classType->id,
                'name' => $classType->name,
                'description' => $classType->description,
                'required_equipment' => $classType->required_equipment,
                'color' => $classType->color,
                'icon' => $classType->icon,
                'default_capacity' => $classType->default_capacity,
            ],
        ]);
    }

    public function update(UpdateClassTypeRequest $request, ClassType $classType, UpdateClassType $updateClassType): RedirectResponse
    {
        $updateClassType->handle($classType, $request->toData());

        return redirect()->route('admin.class-types.index')
            ->with('success', 'Typ zajęć został zaktualizowany.');
    }

    public function destroy(ClassType $classType): RedirectResponse
    {
        $classType->delete();

        return redirect()->route('admin.class-types.index')
            ->with('success', 'Typ zajęć został usunięty.');
    }
}
