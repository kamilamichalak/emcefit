<?php

namespace App\Domain\Memberships\Enums;

/**
 * Tryb karnetu — sekcja 3/4 spec.
 */
enum MembershipMode: string
{
    case Zamkniety = 'zamkniety';        // abonament zamkniety z rezerwacja stalego miejsca
    case Otwarty = 'otwarty';            // pakiet X wejsc, bez gwarancji stalego miejsca
    case BezLimitu = 'bez_limitu';       // miesieczny, nielimitowana liczba wejsc
    case Jednorazowe = 'jednorazowe';    // wejscie jednorazowe / dodatkowy trening
}
