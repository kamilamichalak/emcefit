<?php

namespace App\Support;

use App\Domain\Memberships\Models\Membership;
use Carbon\CarbonImmutable;

/**
 * Termin wpłaty za karnet (ustalone z Kamilą 23.11.2026): do 28. dnia miesiąca
 * poprzedzającego karnet — zapisy otwieramy wcześniej, więc jest kilka dni na wpłatę.
 * Gdy ten dzień już minął (zapis po 28. albo w trakcie miesiąca) — 3 dni od zapisu.
 *
 * Termin jest informacyjny: system niczego sam nie usuwa (decyzja Kamili).
 */
final class PaymentDeadline
{
    public const DAY_OF_MONTH = 28;

    public const FALLBACK_DAYS = 3;

    public static function for(Membership $membership): CarbonImmutable
    {
        return self::forMonth(CarbonImmutable::parse($membership->start_date));
    }

    public static function forMonth(CarbonImmutable $month): CarbonImmutable
    {
        $deadline = $month
            ->startOfMonth()
            ->subMonthNoOverflow()
            ->setDay(self::DAY_OF_MONTH)
            ->endOfDay();

        return $deadline->isPast()
            ? CarbonImmutable::today()->addDays(self::FALLBACK_DAYS)->endOfDay()
            : $deadline;
    }

    public static function label(Membership $membership): string
    {
        return self::for($membership)->translatedFormat('j F');
    }
}
