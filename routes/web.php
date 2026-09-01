<?php

use App\Http\Controllers\Admin\ClassGroupController;
use App\Http\Controllers\Admin\ClassTypeController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MembershipController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function (Request $request) {
    if ($request->user()?->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    }

    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Klienci
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
        Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
        Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
        Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::patch('clients/{client}/status', [ClientController::class, 'updateStatus'])->name('clients.status');

        // Karnety klienta
        Route::get('clients/{client}/memberships/create', [MembershipController::class, 'create'])->name('clients.memberships.create');
        Route::post('clients/{client}/memberships', [MembershipController::class, 'store'])->name('clients.memberships.store');
        Route::delete('memberships/{membership}', [MembershipController::class, 'destroy'])->name('memberships.destroy');

        // Płatności
        Route::get('memberships/{membership}/payments/create', [PaymentController::class, 'create'])->name('memberships.payments.create');
        Route::post('memberships/{membership}/payments', [PaymentController::class, 'store'])->name('memberships.payments.store');
        Route::patch('payments/{payment}/status', [PaymentController::class, 'updateStatus'])->name('payments.status');

        // Typy zajęć (słownik) — niezależne od grafiku
        Route::get('class-types', [ClassTypeController::class, 'index'])->name('class-types.index');
        Route::get('class-types/create', [ClassTypeController::class, 'create'])->name('class-types.create');
        Route::post('class-types', [ClassTypeController::class, 'store'])->name('class-types.store');
        Route::get('class-types/{classType}/edit', [ClassTypeController::class, 'edit'])->name('class-types.edit');
        Route::put('class-types/{classType}', [ClassTypeController::class, 'update'])->name('class-types.update');
        Route::delete('class-types/{classType}', [ClassTypeController::class, 'destroy'])->name('class-types.destroy');

        // Wzorzec tygodniowy grafiku (class_groups)
        Route::get('class-groups', [ClassGroupController::class, 'index'])->name('class-groups.index');
        Route::get('class-groups/create', [ClassGroupController::class, 'create'])->name('class-groups.create');
        Route::post('class-groups', [ClassGroupController::class, 'store'])->name('class-groups.store');
        Route::get('class-groups/{classGroup}/edit', [ClassGroupController::class, 'edit'])->name('class-groups.edit');
        Route::put('class-groups/{classGroup}', [ClassGroupController::class, 'update'])->name('class-groups.update');
        Route::delete('class-groups/{classGroup}', [ClassGroupController::class, 'destroy'])->name('class-groups.destroy');
    });

require __DIR__.'/auth.php';
