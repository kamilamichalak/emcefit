<?php

namespace App\Domain\Clients\Enums;

enum ClientStatus: string
{
    case Active = 'aktywny';
    case Inactive = 'nieaktywny';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktywny',
            self::Inactive => 'Nieaktywny',
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
