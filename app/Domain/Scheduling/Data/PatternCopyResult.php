<?php

namespace App\Domain\Scheduling\Data;

/**
 * Wynik kopiowania wzorca tygodniowego na kolejny miesiac.
 */
final readonly class PatternCopyResult
{
    private function __construct(
        public string $status,     // 'copied' | 'conflict' | 'empty'
        public int $count,         // liczba skopiowanych (copied) albo kolidujacych (conflict) zajec
        public string $nextMonth,  // 'Y-m'
    ) {}

    public static function copied(int $count, string $nextMonth): self
    {
        return new self('copied', $count, $nextMonth);
    }

    public static function conflict(int $count, string $nextMonth): self
    {
        return new self('conflict', $count, $nextMonth);
    }

    public static function emptySource(string $nextMonth): self
    {
        return new self('empty', 0, $nextMonth);
    }
}
