<?php

namespace App\Http\Controllers;

use App\Domain\Clients\Actions\SendClientInvitation;
use App\Domain\Clients\Models\HelpRequest;
use App\Mail\HelpRequestReceivedMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Formularz „Pomoc” — dostępny także bez logowania (np. gdy klientka nie może się zalogować).
 * Zgłoszenie zapisuje się w bazie i (jeśli skonfigurowano SUPPORT_EMAIL) przychodzi mailem.
 */
class HelpRequestController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Help', [
            'prefill' => [
                'name' => $user?->name ?? '',
                'email' => $user?->email && ! SendClientInvitation::isPlaceholderEmail($user->email) ? $user->email : '',
            ],
            'from' => Str::limit((string) $request->headers->get('referer'), 500, ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'from' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Podaj imię i nazwisko.',
            'email.required' => 'Podaj adres e-mail, żebyśmy mogli odpowiedzieć.',
            'email.email' => 'Podaj poprawny adres e-mail.',
            'message.required' => 'Napisz, w czym możemy pomóc.',
            'message.max' => 'Wiadomość jest za długa (maks. 5000 znaków).',
        ]);

        $helpRequest = HelpRequest::create([
            'user_id' => $request->user()?->id,
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'message' => trim($data['message']),
            'page_url' => $data['from'] ?? null,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        if ($to = config('services.support.email')) {
            try {
                Mail::to($to)->send(new HelpRequestReceivedMail($helpRequest));
            } catch (Throwable $e) {
                // zgłoszenie jest w bazie — brak maila nie może zablokować klientki
                Log::error('Nie udało się wysłać powiadomienia o zgłoszeniu', ['id' => $helpRequest->id, 'error' => $e->getMessage()]);
            }
        }

        return redirect()->route('help.create')
            ->with('success', 'Dziękujemy! Zgłoszenie dotarło. Odpowiemy mailem, zwykle w ciągu 1–2 dni.');
    }
}
