<?php

namespace App\Http\Controllers;

use App\Domain\Clients\Actions\ResetClientPassword;
use App\Domain\Clients\Models\Client;
use App\Http\Requests\ResetClientPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reset hasła klienta z ręcznie wygenerowanego, podpisanego linku (Prompt 18) —
 * bez wysyłki maili, analogicznie do aktywacji konta z sekcji 12.
 */
class ClientPasswordResetController extends Controller
{
    public function show(Request $request, Client $client): Response
    {
        if ($invalid = $this->linkProblem($request)) {
            return $invalid;
        }

        return Inertia::render('Auth/ResetClientPassword', [
            'client' => ['name' => $client->user->name, 'email' => $client->user->email],
            'submitUrl' => $request->fullUrl(),
        ]);
    }

    public function store(ResetClientPasswordRequest $request, Client $client, ResetClientPassword $resetClientPassword): RedirectResponse|Response
    {
        if ($invalid = $this->linkProblem($request)) {
            return $invalid;
        }

        $client = $resetClientPassword->handle($client, $request->password());

        Auth::login($client->user);
        $request->session()->regenerate();

        return redirect()->route('client.dashboard')
            ->with('success', 'Hasło zostało zmienione. Witaj z powrotem!');
    }

    private function linkProblem(Request $request): ?Response
    {
        if ($request->hasValidSignature()) {
            return null;
        }

        $expired = is_numeric($request->query('expires'))
            && (int) $request->query('expires') < now()->getTimestamp();

        return Inertia::render('Auth/LinkInvalid', [
            'title' => 'Nie można zresetować hasła',
            'message' => $expired
                ? 'Link do zresetowania hasła wygasł. Poproś klub o wygenerowanie nowego.'
                : 'Link do zresetowania hasła jest nieprawidłowy. Sprawdź, czy skopiowano go w całości.',
        ]);
    }
}
