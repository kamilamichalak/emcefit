<?php

namespace App\Http\Controllers\Client;

use App\Domain\Memberships\Models\Membership;
use App\Http\Controllers\Controller;
use App\Support\PaymentDeadline;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** „Płatności i regulamin”: numer konta, tytuł przelewu, ile i do kiedy, regulamin. */
class PaymentInfoController extends Controller
{
    public function show(Request $request): Response
    {
        $client = $request->user()->client;
        $thisMonth = CarbonImmutable::today()->startOfMonth();

        $memberships = $client
            ? $client->memberships()
                ->whereIn('start_date', [$thisMonth->toDateString(), $thisMonth->addMonthNoOverflow()->toDateString()])
                ->with(['membershipType', 'payments'])
                ->orderBy('start_date')
                ->get()
                ->map(fn (Membership $m) => [
                    'month_label' => $m->start_date->translatedFormat('F'),
                    'type_name' => $m->membershipType->name,
                    'price' => $m->price_locked,
                    'amount_due' => $m->amountDue(),
                    'deadline' => $m->amountDue() > 0 ? PaymentDeadline::label($m) : null,
                    'title' => 'zajęcia fitness, '.$request->user()->name.', '.$m->start_date->translatedFormat('F Y'),
                ])
                ->values()
            : collect();

        return Inertia::render('Client/PaymentInfo', [
            'account' => config('club.bank_account'),
            'memberships' => $memberships,
            'defaultTitle' => 'zajęcia fitness, '.$request->user()->name.', [miesiąc]',
            'regulaminHtml' => Str::markdown(file_get_contents(resource_path('content/regulamin.md')), ['html_input' => 'escape']),
        ]);
    }
}
