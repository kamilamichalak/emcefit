<?php

namespace App\Http\Controllers;

use App\Domain\Clients\Actions\ActivateClientAccount;
use App\Domain\Clients\Models\Client;
use App\Http\Requests\ActivateAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClientActivationController extends Controller
{
    public function show(Request $request, Client $client): Response
    {
        if ($invalid = $this->linkProblem($request, $client)) {
            return $invalid;
        }

        return Inertia::render('Auth/ActivateAccount', [
            'client' => [
                'name' => $client->user->name,
                'email' => $client->user->email,
            ],
            'regulaminHtml' => $this->regulaminHtml(),
            'submitUrl' => $request->fullUrl(),
        ]);
    }

    public function store(ActivateAccountRequest $request, Client $client, ActivateClientAccount $activateClientAccount): RedirectResponse|Response
    {
        if ($invalid = $this->linkProblem($request, $client)) {
            return $invalid;
        }

        $client = $activateClientAccount->handle($client, $request->password());

        Auth::login($client->user);
        $request->session()->regenerate();

        return redirect()->route('client.dashboard')
            ->with('success', 'Konto aktywowane. Witaj w panelu!');
    }

    /**
     * Zwraca stronę błędu, gdy link jest nieprawidłowy/wygasły/wykorzystany, albo null.
     */
    private function linkProblem(Request $request, Client $client): ?Response
    {
        if (! $request->hasValidSignature()) {
            $expired = is_numeric($request->query('expires'))
                && (int) $request->query('expires') < now()->getTimestamp();

            return Inertia::render('Auth/ActivationInvalid', [
                'reason' => $expired ? 'expired' : 'invalid',
            ]);
        }

        if ($client->isActivated()) {
            return Inertia::render('Auth/ActivationInvalid', ['reason' => 'used']);
        }

        return null;
    }

    private function regulaminHtml(): string
    {
        return Str::markdown(
            file_get_contents(resource_path('content/regulamin.md')),
            ['html_input' => 'escape'],
        );
    }
}
