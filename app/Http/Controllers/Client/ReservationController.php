<?php

namespace App\Http\Controllers\Client;

use App\Domain\Reservations\Actions\CancelClientReservation;
use App\Domain\Reservations\Actions\PromoteFromWaitlist;
use App\Domain\Reservations\Enums\ReservationKind;
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

        // Bezpiecznik: klientka musi świadomie potwierdzić zwolnienie, na którym coś traci.
        if ($cancelReservation->causesLoss($reservation) && ! $request->acknowledgesLate()) {
            return back()->withErrors([
                'reservation' => match ($reservation->kind) {
                    ReservationKind::Makeup => 'To zajęcia odrabiane — po zwolnieniu miejsca odrobienie przepada.',
                    ReservationKind::OpenEntry => 'Zwolnienie miejsca później niż godzinę przed zajęciami oznacza utratę wejścia z karnetu.',
                    default => 'Zgodnie z regulaminem, zwolnienie miejsca później niż godzinę przed zajęciami nie daje prawa do odrobienia.',
                },
            ]);
        }

        $granted = $cancelReservation->handle($reservation);

        // Zwolniło się miejsce — wciągnij pierwszą osobę z listy oczekujących (Prompt 14a).
        $promoteFromWaitlist->handle($reservation->classSchedule);

        return back()->with('success', match (true) {
            $granted => 'Miejsce zwolnione, masz teraz prawo do odrobienia w tym miesiącu.',
            $reservation->kind === ReservationKind::Makeup => 'Miejsce zwolnione. Odrobienie zostało wykorzystane.',
            $reservation->kind === ReservationKind::OpenEntry && ! $reservation->late_release => 'Miejsce zwolnione, wejście wróciło na Twój karnet.',
            $reservation->kind === ReservationKind::OpenEntry => 'Miejsce zwolnione. Wejście przepadło — do startu zostało mniej niż godzina.',
            default => 'Miejsce zwolnione. Bez prawa do odrobienia — do startu zostało mniej niż godzina.',
        });
    }
}
