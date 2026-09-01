<?php

namespace App\Domain\Scheduling\Enums;

/**
 * Dzien tygodnia wg ISO-8601 (1 = poniedzialek ... 7 = niedziela) — zgodne z
 * Carbon::dayOfWeekIso. Faza 2 (grafik) operuje na dniach roboczych 1..5.
 */
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Poniedziałek',
            self::Tuesday => 'Wtorek',
            self::Wednesday => 'Środa',
            self::Thursday => 'Czwartek',
            self::Friday => 'Piątek',
            self::Saturday => 'Sobota',
            self::Sunday => 'Niedziela',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Monday => 'Pon',
            self::Tuesday => 'Wt',
            self::Wednesday => 'Śr',
            self::Thursday => 'Czw',
            self::Friday => 'Pt',
            self::Saturday => 'Sob',
            self::Sunday => 'Ndz',
        };
    }

    /**
     * Dni robocze pon–pt.
     *
     * @return list<self>
     */
    public static function workdays(): array
    {
        return [self::Monday, self::Tuesday, self::Wednesday, self::Thursday, self::Friday];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $day): array => ['value' => $day->value, 'label' => $day->label()],
            self::workdays(),
        );
    }
}
