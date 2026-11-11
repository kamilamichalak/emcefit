<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Clients\Actions\SendPasswordResetLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ForgotPasswordController extends Controller
{
    public const STATUS = 'Jeśli ten adres jest w naszym systemie, za chwilę dostaniesz na niego maila z linkiem do ustawienia hasła. Sprawdź też folder spam.';

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request, SendPasswordResetLink $sendPasswordResetLink): RedirectResponse
    {
        $request->validate(
            ['email' => ['required', 'email', 'max:255']],
            [
                'email.required' => 'Podaj adres e-mail.',
                'email.email' => 'Podaj poprawny adres e-mail.',
            ],
        );

        try {
            $sendPasswordResetLink->handle($request->string('email')->toString());
        } catch (Throwable $e) {
            Log::error('Nie udało się wysłać linku do resetu hasła', ['error' => $e->getMessage()]);

            return back()->withErrors(['email' => 'Nie udało się wysłać maila. Spróbuj za chwilę albo napisz do studia.']);
        }

        return back()->with('status', self::STATUS);
    }
}
