<?php

namespace App\Http\Controllers\Client;

use App\Domain\Reservations\Actions\BookFreeSpot;
use App\Domain\Reservations\Enums\ReservationKind;
use App\Domain\Reservations\FreeSpots;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * „Wolne miejsca”: odrabianie i zapisy z karnetu otwartego — niezależnie od okna zapisów.
 */
class FreeSpotController extends Controller
{
    public function index(Request $request, FreeSpots $freeSpots): Response
    {
        $client = $request->user()->client;
        $open = $client ? $freeSpots->openEntitlement($client) : null;

        return Inertia::render('Client/FreeSpots', [
            'makeupCredits' => $client ? $freeSpots->makeupCreditsCount($client) : 0,
            'open' => $open ? [
                'type_name' => $open['membership']->membershipType->name,
                'remaining' => $open['remaining'],
                'valid_to' => $open['to']->translatedFormat('j F'),
            ] : null,
            'days' => $client ? $this->presentByDay($freeSpots->available($client)) : [],
        ]);
    }

    public function store(Request $request, ClassSchedule $occurrence, BookFreeSpot $bookFreeSpot): RedirectResponse
    {
        $client = $request->user()->client;
        abort_if($client === null, 403);

        try {
            $reservation = $bookFreeSpot->handle($client, $occurrence);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $when = Str::ucfirst($occurrence->date->translatedFormat('l, j F')).', '.CarbonImmutable::parse($occurrence->start_time)->format('H:i');

        return redirect()->route('client.free-spots.index')->with('success', $reservation->kind === ReservationKind::Makeup
            ? "Zapisane! Odrabiasz zajęcia: {$when}."
            : "Zapisane! {$when} — wykorzystano 1 wejście z karnetu.");
    }

    /**
     * @return list<array{label: string, items: list<array<string, mixed>>}>
     */
    private function presentByDay($available): array
    {
        return $available
            ->groupBy(fn (array $row) => $row['occurrence']->date->toDateString())
            ->map(fn ($rows, $date) => [
                'label' => Str::ucfirst(CarbonImmutable::parse($date)->translatedFormat('l, j F')),
                'items' => $rows->map(function (array $row): array {
                    $occurrence = $row['occurrence'];
                    $start = CarbonImmutable::parse($occurrence->start_time);

                    return [
                        'id' => $occurrence->id,
                        'start_time' => $start->format('H:i'),
                        'end_time' => $start->addMinutes($occurrence->classGroup->duration_minutes)->format('H:i'),
                        'type_name' => $occurrence->classGroup->classType->name,
                        'type_color' => $occurrence->classGroup->classType->color,
                        'type_icon' => $occurrence->classGroup->classType->icon,
                        'free' => $row['free'],
                        'kind' => $row['kind']->value,
                    ];
                })->values()->all(),
            ])
            ->values()
            ->all();
    }
}
