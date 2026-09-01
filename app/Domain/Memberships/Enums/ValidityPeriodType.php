<?php

namespace App\Domain\Memberships\Enums;

/**
 * Tryb liczenia waznosci karnetu — sekcja 4 spec.
 */
enum ValidityPeriodType: string
{
    case MiesiacKalendarzowy = 'miesiac_kalendarzowy';
    case TygodnieOdPierwszegoWejscia = 'tygodnie_od_pierwszego_wejscia';
}
