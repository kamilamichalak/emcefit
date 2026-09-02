<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Actions\RegisterPayment;
use App\Domain\Payments\Actions\SetPaymentStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Reservations\Actions\ConfirmReservationsForPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentRequest;
use App\Http\Requests\Admin\UpdatePaymentStatusRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function create(Membership $membership): Response
    {
        $membership->load(['client.user:id,name', 'membershipType:id,name,price']);

        return Inertia::render('Admin/Payments/Create', [
            'membership' => [
                'id' => $membership->id,
                'client_id' => $membership->client_id,
                'client_name' => $membership->client->user->name,
                'type_name' => $membership->membershipType->name,
                'suggested_amount' => $membership->price_locked,
            ],
        ]);
    }

    public function store(StorePaymentRequest $request, Membership $membership, RegisterPayment $registerPayment, ConfirmReservationsForPayment $confirmReservations): RedirectResponse
    {
        $payment = $registerPayment->handle($membership, $request->toData());

        $message = 'Płatność została zarejestrowana.';

        if ($payment->status === PaymentStatus::Settled) {
            $message = $this->settlementSummary($confirmReservations->handle($payment)) ?? $message;
        }

        return redirect()->route('admin.clients.show', $membership->client_id)
            ->with('success', $message);
    }

    public function updateStatus(UpdatePaymentStatusRequest $request, Payment $payment, SetPaymentStatus $setPaymentStatus, ConfirmReservationsForPayment $confirmReservations): RedirectResponse
    {
        $setPaymentStatus->apply($payment, $request->status());

        $message = 'Status płatności został zaktualizowany.';

        if ($payment->status === PaymentStatus::Settled) {
            $message = $this->settlementSummary($confirmReservations->handle($payment)) ?? $message;
        }

        return back()->with('success', $message);
    }

    /**
     * Krótkie podsumowanie po zaksięgowaniu wpłaty (spec sekcja 16, pkt 4).
     *
     * @param  array{confirmed: list<string>, waitlisted: list<string>}  $result
     */
    private function settlementSummary(array $result): ?string
    {
        $confirmed = $result['confirmed'];
        $waitlisted = $result['waitlisted'];

        if ($confirmed === [] && $waitlisted === []) {
            return null;
        }

        $message = 'Płatność zaksięgowana. Potwierdzone rezerwacje: '.count($confirmed).'.';

        if ($waitlisted !== []) {
            $message .= ' Na liście oczekujących: '.count($waitlisted).' ('.implode('; ', $waitlisted).').';
        }

        return $message;
    }
}
