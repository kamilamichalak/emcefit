<?php

namespace App\Http\Controllers\Client;

use App\Domain\Reservations\Actions\CancelClientReservation;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CancelReservationRequest;
use Illuminate\Http\RedirectResponse;

class ReservationController extends Controller
{
    public function cancel(CancelReservationRequest $request, Reservation $reservation, CancelClientReservation $cancelReservation): RedirectResponse
    {
        $reservation->load('classSchedule');

        if ($reservation->status !== ReservationStatus::Confirmed) {
            return back()->with('error', 'Tej rezerwacji nie można już odwołać.');
        }

        if ($reservation->startsAt()->isPast()) {
            return back()->with('error', 'Nie można odwołać zajęć, które już się odbyły.');
        }

        // Bezpiecznik: klient musi świadomie potwierdzić odwołanie bez prawa do odrobienia.
        if (! $cancelReservation->grantsMakeupCredit($reservation) && ! $request->acknowledgesLate()) {
            return back()->withErrors([
                'reservation' => 'Zgodnie z regulaminem, odwołanie później niż godzinę przed zajęciami nie daje prawa do odrobienia.',
            ]);
        }

        $granted = $cancelReservation->handle($reservation);

        return back()->with('success', $granted
            ? 'Zajęcia odwołane, masz teraz prawo do odrobienia w tym miesiącu.'
            : 'Zajęcia odwołane. Bez prawa do odrobienia — do startu zostało mniej niż godzina.');
    }
}
