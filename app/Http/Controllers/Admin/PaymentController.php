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
use App\Mail\PaymentSettledMail;
use App\Support\ClientMailer;
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
                // tytuł wg regulaminu pkt 30 — ten sam, który klient widzi po zgłoszeniu
                'suggested_title' => 'zajęcia fitness, '.$membership->client->user->name.', '.$membership->start_date->translatedFormat('F Y'),
            ],
        ]);
    }

    public function store(StorePaymentRequest $request, Membership $membership, RegisterPayment $registerPayment, ConfirmReservationsForPayment $confirmReservations): RedirectResponse
    {
        $payment = $registerPayment->handle($membership, $request->toData());

        $message = 'Płatność została zarejestrowana.';

        if ($payment->status === PaymentStatus::Settled) {
            $result = $confirmReservations->handle($payment);
            $message = $this->settlementSummary($result) ?? $message;
            $this->notifyClient($payment, $result);
        }

        return redirect()->route('admin.clients.show', $membership->client_id)
            ->with('success', $message);
    }

    public function updateStatus(UpdatePaymentStatusRequest $request, Payment $payment, SetPaymentStatus $setPaymentStatus, ConfirmReservationsForPayment $confirmReservations): RedirectResponse
    {
        $wasSettled = $payment->status === PaymentStatus::Settled;
        $setPaymentStatus->apply($payment, $request->status());

        $message = 'Status płatności został zaktualizowany.';

        if ($payment->status === PaymentStatus::Settled) {
            $result = $confirmReservations->handle($payment);
            $message = $this->settlementSummary($result) ?? $message;

            if (! $wasSettled) {
                $this->notifyClient($payment, $result);
            }
        }

        return back()->with('success', $message);
    }

    /**
     * Mail do klientki: wpłata zaksięgowana (+ zajęcia, na które trafiła na listę oczekujących).
     *
     * @param  array{confirmed: list<string>, waitlisted: list<string>}  $result
     */
    private function notifyClient(Payment $payment, array $result): void
    {
        $membership = $payment->membership()->with('client.user')->first();

        if ($membership !== null) {
            ClientMailer::send($membership->client, new PaymentSettledMail($membership, $result['waitlisted']));
        }
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
