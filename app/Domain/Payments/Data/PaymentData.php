<?php

namespace App\Domain\Payments\Data;

/**
 * Dane recznie rejestrowanej platnosci (wylacznie przelew bankowy — spec sekcja 8).
 */
final readonly class PaymentData
{
    public function __construct(
        public float $amount,
        public ?string $reportedDate,
        public ?string $settledDate,
        public bool $markSettled,
        public ?string $transferTitle,
    ) {}
}
