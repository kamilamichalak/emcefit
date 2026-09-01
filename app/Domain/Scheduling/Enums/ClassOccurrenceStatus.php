<?php

namespace App\Domain\Scheduling\Enums;

/**
 * Status pojedynczego wystapienia zajec (class_schedule) — spec sekcja 4.
 */
enum ClassOccurrenceStatus: string
{
    case Planned = 'planowane';
    case Cancelled = 'odwolane';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planowane',
            self::Cancelled => 'Odwołane',
        };
    }
}
