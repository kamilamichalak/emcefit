<?php

namespace App\Http\Controllers;

use App\Domain\Clients\Models\EmailSubmission;
use App\Http\Requests\StoreEmailSubmissionRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Publiczny formularz zbierania prawdziwych e-maili klientek (bez logowania).
 * Strona nigdy nie pokazuje innych wpisów — po wysłaniu tylko podziękowanie.
 */
class EmailSubmissionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('EmailSubmission');
    }

    public function store(StoreEmailSubmissionRequest $request): RedirectResponse
    {
        EmailSubmission::create([
            'full_name' => trim($request->string('full_name')->toString()),
            'email' => strtolower(trim($request->string('email')->toString())),
            'consent_at' => now(),
        ]);

        return redirect()->route('email-submission.create')->with('success', 'Dziękujemy! Twoje dane zostały zapisane.');
    }
}
