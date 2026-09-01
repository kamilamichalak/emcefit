<?php

namespace App\Domain\Scheduling\Data;

/**
 * Wynik generowania harmonogramu miesiecznego.
 */
final readonly class ScheduleGenerationResult
{
    private function __construct(
        public string $status,   // 'exists' | 'generated'
        public int $created,
        public int $removed,
        public int $existing,
    ) {}

    public static function alreadyExists(int $existing): self
    {
        return new self('exists', 0, 0, $existing);
    }

    public static function generated(int $created, int $removed): self
    {
        return new self('generated', $created, $removed, 0);
    }
}
