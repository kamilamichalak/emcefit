<?php

namespace App\Domain\Scheduling\Data;

/**
 * Wynik skopiowania wzorca tygodniowego do wskazanego miesiaca.
 */
final readonly class PatternCopyResult
{
    private function __construct(
        public string $status,       // 'copied' | 'conflict' | 'empty'
        public int $count,           // liczba skopiowanych (copied) albo kolidujacych wlasnych (conflict) zajec
        public string $targetMonth,  // 'Y-m'
    ) {}

    public static function copied(int $count, string $targetMonth): self
    {
        return new self('copied', $count, $targetMonth);
    }

    public static function conflict(int $count, string $targetMonth): self
    {
        return new self('conflict', $count, $targetMonth);
    }

    public static function emptySource(string $targetMonth): self
    {
        return new self('empty', 0, $targetMonth);
    }
}
