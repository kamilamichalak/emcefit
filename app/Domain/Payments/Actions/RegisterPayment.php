<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Data\PaymentData;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use Carbon\CarbonImmutable;

final class RegisterPayment
{
    /**
     * Rejestruje wplate przypisana do karnetu. Domyslnie w stanie "oczekuje";
     * admin moze od razu oznaczyc jako zaksiegowana (po sprawdzeniu wyciagu).
     */
    public function handle(Membership $membership, PaymentData $data): Payment
    {
        $today = CarbonImmutable::today()->toDateString();

        return $membership->payments()->create([
            'client_id' => $membership->client_id,
            'amount' => $data->amount,
            'reported_date' => $data->reportedDate ?? $today,
            'settled_date' => $data->markSettled ? ($data->settledDate ?? $today) : null,
            'status' => $data->markSettled ? PaymentStatus::Settled : PaymentStatus::Pending,
            'transfer_title' => $data->transferTitle,
        ]);
    }
}
