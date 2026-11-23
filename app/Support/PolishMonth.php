<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Polskie nazwy miesięcy w odmianie: „w grudniu”, „do końca grudnia”. */
final class PolishMonth
{
    private const LOCATIVE = [1 => 'styczniu', 'lutym', 'marcu', 'kwietniu', 'maju', 'czerwcu', 'lipcu', 'sierpniu', 'wrześniu', 'październiku', 'listopadzie', 'grudniu'];

    private const GENITIVE = [1 => 'stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'];

    public static function in(CarbonInterface $date): string
    {
        return 'w '.self::LOCATIVE[$date->month];
    }

    public static function untilEndOf(CarbonInterface $date): string
    {
        return 'do końca '.self::GENITIVE[$date->month];
    }
}
