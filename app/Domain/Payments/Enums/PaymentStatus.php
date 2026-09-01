<?php

namespace App\Domain\Payments\Enums;

/**
 * Payment status — spec section 4.
 */
enum PaymentStatus: string
{
    case Pending = 'oczekuje';
    case Settled = 'zaksiegowana';
    case Cancelled = 'anulowana';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Oczekuje',
            self::Settled => 'Zaksięgowana',
            self::Cancelled => 'Anulowana',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
