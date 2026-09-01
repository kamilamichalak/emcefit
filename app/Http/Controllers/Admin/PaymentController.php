<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Actions\RegisterPayment;
use App\Domain\Payments\Actions\SetPaymentStatus;
use App\Domain\Payments\Models\Payment;
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
                'suggested_amount' => $membership->membershipType->price,
            ],
        ]);
    }

    public function store(StorePaymentRequest $request, Membership $membership, RegisterPayment $registerPayment): RedirectResponse
    {
        $registerPayment->handle($membership, $request->toData());

        return redirect()->route('admin.clients.show', $membership->client_id)
            ->with('success', 'Płatność została zarejestrowana.');
    }

    public function updateStatus(UpdatePaymentStatusRequest $request, Payment $payment, SetPaymentStatus $setPaymentStatus): RedirectResponse
    {
        $setPaymentStatus->apply($payment, $request->status());

        return back()->with('success', 'Status płatności został zaktualizowany.');
    }
}
