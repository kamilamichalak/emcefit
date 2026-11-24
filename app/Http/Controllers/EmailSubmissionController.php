<?php

namespace App\Http\Controllers;

use App\Domain\Clients\Actions\ProcessEmailSubmission;
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

    public function store(StoreEmailSubmissionRequest $request, ProcessEmailSubmission $process): RedirectResponse
    {
        $submission = EmailSubmission::create([
            'full_name' => trim($request->string('full_name')->toString()),
            'email' => strtolower(trim($request->string('email')->toString())),
            'consent_at' => now(),
        ]);

        // konto + zaproszenie od razu (wątpliwe przypadki zostają do decyzji studia);
        // formularz ma zawsze zadziałać — błąd tutaj tylko logujemy
        try {
            $process->handle($submission);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('email-submission.create')->with('success', 'Dziękujemy! Twoje dane zostały zapisane.');
    }
}
