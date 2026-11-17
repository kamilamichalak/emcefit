<?php

namespace App\Http\Controllers;

use App\Domain\Memberships\Models\Membership;
use App\Http\Controllers\Client\DashboardController;
use App\Http\Requests\ProfileUpdateRequest;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'memberships' => $this->memberships($request),
        ]);
    }

    /**
     * Karnety klientki na ten i następny miesiąc (dla personelu — pusta lista).
     *
     * @return list<array<string, mixed>>
     */
    private function memberships(Request $request): array
    {
        $client = $request->user()->client;

        if ($client === null) {
            return [];
        }

        $thisMonth = CarbonImmutable::today()->startOfMonth();
        $months = [$thisMonth, $thisMonth->addMonthNoOverflow()];

        return $client->memberships()
            ->whereIn('start_date', array_map(fn ($m) => $m->toDateString(), $months))
            ->with(['membershipType:id,name', 'payments'])
            ->orderBy('start_date')
            ->get()
            ->map(fn (Membership $m) => DashboardController::presentMembership($m, CarbonImmutable::parse($m->start_date)))
            ->all();
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Konta personelu (admin/trener) nie moga byc usuwane samoobslugowo — spec 8a.
        abort_if(
            $request->user()->hasRole(['admin', 'trainer']),
            403,
            'Konta administratora i trenera nie można usunąć z tego ekranu.',
        );

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
