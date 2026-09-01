<?php

namespace App\Domain\Memberships\Enums;

/**
 * How a membership's validity is counted — spec section 4.
 */
enum ValidityPeriodType: string
{
    case CalendarMonth = 'miesiac_kalendarzowy';
    case WeeksFromFirstEntry = 'tygodnie_od_pierwszego_wejscia';
}
