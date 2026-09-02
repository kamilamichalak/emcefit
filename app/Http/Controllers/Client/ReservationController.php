<?php

namespace App\Http\Controllers\Client;

use App\Domain\Reservations\Actions\CancelClientReservation;
use App\Domain\Reservations\Actions\PromoteFromWaitlist;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CancelReservationRequest;
use Illuminate\Http\RedirectResponse;

class ReservationController extends Controller
{
    public function cancel(
        CancelReservationRequest $request,
        Reservation $reservation,
        CancelClientReservation $cancelReservation,
        PromoteFromWaitlist $promoteFromWaitlist,
    ): RedirectResponse {
        $reservation->load('classSchedule.classGroup');

        if ($reservation->status !== ReservationStatus::Confirmed) {
            return back()->with('error', 'Tego miejsca nie można już zwolnić.');
        }

        if ($reservation->startsAt()->isPast()) {
            return back()->with('error', 'Nie można zwolnić miejsca na zajęcia, które już się odbyły.');
        }

        // Bezpiecznik: klient musi świadomie potwierdzić zwolnienie bez prawa do odrobienia.
        if (! $cancelReservation->grantsMakeupCredit($reservation) && ! $request->acknowledgesLate()) {
            return back()->withErrors([
                'reservation' => 'Zgodnie z regulaminem, zwolnienie miejsca później niż godzinę przed zajęciami nie daje prawa do odrobienia.',
            ]);
        }

        $granted = $cancelReservation->handle($reservation);

        // Zwolniło się miejsce — wciągnij pierwszą osobę z listy oczekujących (Prompt 14a).
        $promoteFromWaitlist->handle($reservation->classSchedule);

        return back()->with('success', $granted
            ? 'Miejsce zwolnione, masz teraz prawo do odrobienia w tym miesiącu.'
            : 'Miejsce zwolnione. Bez prawa do odrobienia — do startu zostało mniej niż godzina.');
    }
}
