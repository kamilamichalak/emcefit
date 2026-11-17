<?php

namespace App\Http\Controllers\Client;

use App\Domain\Clients\Models\Client;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Klientka wybiera, które powiadomienia mailowe chce dostawać. */
class MailPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        $client = $request->user()->client;

        return Inertia::render('Client/MailPreferences', [
            'options' => collect(Client::MAIL_PREFERENCES)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values(),
            'preferences' => $client?->mailPreferences() ?? [],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $client = $request->user()->client;
        abort_if($client === null, 404);

        $rules = collect(Client::MAIL_PREFERENCES)->mapWithKeys(fn ($label, $key) => ["preferences.$key" => ['required', 'boolean']])->all();
        $data = $request->validate(['preferences' => ['required', 'array']] + $rules);

        $client->update([
            'mail_preferences' => collect(Client::MAIL_PREFERENCES)
                ->mapWithKeys(fn ($label, $key) => [$key => (bool) $data['preferences'][$key]])
                ->all(),
        ]);

        return back()->with('success', 'Zapisano ustawienia powiadomień.');
    }
}
