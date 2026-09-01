<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use Carbon\CarbonImmutable;

final class SetPaymentStatus
{
    /**
     * Oznacza wplate jako zaksiegowana (admin po sprawdzeniu wyciagu bankowego).
     */
    public function settle(Payment $payment, ?string $settledDate = null): Payment
    {
        $payment->update([
            'status' => PaymentStatus::Settled,
            'settled_date' => $settledDate ?? CarbonImmutable::today()->toDateString(),
        ]);

        return $payment;
    }

    /**
     * Cofa do stanu "oczekuje" (odhaczenie) — czysci date zaksiegowania.
     */
    public function markPending(Payment $payment): Payment
    {
        $payment->update([
            'status' => PaymentStatus::Pending,
            'settled_date' => null,
        ]);

        return $payment;
    }

    public function cancel(Payment $payment): Payment
    {
        $payment->update([
            'status' => PaymentStatus::Cancelled,
            'settled_date' => null,
        ]);

        return $payment;
    }

    public function apply(Payment $payment, PaymentStatus $status): Payment
    {
        return match ($status) {
            PaymentStatus::Settled => $this->settle($payment),
            PaymentStatus::Pending => $this->markPending($payment),
            PaymentStatus::Cancelled => $this->cancel($payment),
        };
    }
}
