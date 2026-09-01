<?php

namespace Database\Seeders;

use App\Domain\Memberships\Enums\MembershipMode;
use App\Domain\Memberships\Enums\ValidityPeriodType;
use App\Domain\Memberships\Models\MembershipType;
use Illuminate\Database\Seeder;

class MembershipTypeSeeder extends Seeder
{
    /**
     * Cennik klubu EMCEFIT — dane startowe Fazy 1 (spec 8a: edycja przez admina
     * dopiero w Fazie 3). Zrodlo: grafika "EMCEFIT CENNIK" z regulaminu.
     *
     * Ceny w PLN (brutto), zgodnie z cennikiem.
     *
     * Zalozenia interpretacyjne (do ewentualnej korekty):
     * - krotkoterminowe abonamenty zamkniete ("2 tygodnie" / "3 tygodnie") maja
     *   validity_period_type = tygodnie_od_pierwszego_wejscia (jedyna opcja poza
     *   miesiacem kalendarzowym); czy liczyc od zakupu czy od 1. wejscia — TBD
     * - "Wejscie jednorazowe" oraz "Dodatkowy trening" => mode = jednorazowe,
     *   entry_count = 1
     * - "Abonament bez limitu" (waznosc 1..ostatni dzien miesiaca) => miesiac_kalendarzowy
     */
    public function run(): void
    {
        $month = ValidityPeriodType::CalendarMonth;
        $weeks = ValidityPeriodType::WeeksFromFirstEntry;

        $types = [
            // --- Abonament zamkniety (z rezerwacja stalego miejsca) ---
            ['name' => 'Zamknięty 1x/tydzień — miesięczny', 'mode' => MembershipMode::Closed, 'sessions_per_week' => 1, 'entry_count' => null, 'validity_period_type' => $month, 'validity_period_value' => 1, 'price' => 100.00],
            ['name' => 'Zamknięty 2x/tydzień — miesięczny', 'mode' => MembershipMode::Closed, 'sessions_per_week' => 2, 'entry_count' => null, 'validity_period_type' => $month, 'validity_period_value' => 1, 'price' => 160.00],
            ['name' => 'Zamknięty 2x/tydzień — 2 tygodnie', 'mode' => MembershipMode::Closed, 'sessions_per_week' => 2, 'entry_count' => null, 'validity_period_type' => $weeks, 'validity_period_value' => 2, 'price' => 100.00],
            ['name' => 'Zamknięty 3x/tydzień — miesięczny', 'mode' => MembershipMode::Closed, 'sessions_per_week' => 3, 'entry_count' => null, 'validity_period_type' => $month, 'validity_period_value' => 1, 'price' => 210.00],
            ['name' => 'Zamknięty 3x/tydzień — 3 tygodnie', 'mode' => MembershipMode::Closed, 'sessions_per_week' => 3, 'entry_count' => null, 'validity_period_type' => $weeks, 'validity_period_value' => 3, 'price' => 170.00],
            ['name' => 'Zamknięty 3x/tydzień — 2 tygodnie', 'mode' => MembershipMode::Closed, 'sessions_per_week' => 3, 'entry_count' => null, 'validity_period_type' => $weeks, 'validity_period_value' => 2, 'price' => 130.00],
            ['name' => 'Zamknięty 4x/tydzień — miesięczny', 'mode' => MembershipMode::Closed, 'sessions_per_week' => 4, 'entry_count' => null, 'validity_period_type' => $month, 'validity_period_value' => 1, 'price' => 260.00],
            ['name' => 'Zamknięty 4x/tydzień — 3 tygodnie', 'mode' => MembershipMode::Closed, 'sessions_per_week' => 4, 'entry_count' => null, 'validity_period_type' => $weeks, 'validity_period_value' => 3, 'price' => 210.00],
            ['name' => 'Zamknięty 4x/tydzień — 2 tygodnie', 'mode' => MembershipMode::Closed, 'sessions_per_week' => 4, 'entry_count' => null, 'validity_period_type' => $weeks, 'validity_period_value' => 2, 'price' => 160.00],

            // --- Abonament otwarty (bez gwarancji stalego miejsca, waznosc 5 tygodni od 1. wejscia) ---
            ['name' => 'Otwarty — 4 wejścia (5 tygodni)', 'mode' => MembershipMode::Open, 'sessions_per_week' => null, 'entry_count' => 4, 'validity_period_type' => $weeks, 'validity_period_value' => 5, 'price' => 80.00],
            ['name' => 'Otwarty — 8 wejść (5 tygodni)', 'mode' => MembershipMode::Open, 'sessions_per_week' => null, 'entry_count' => 8, 'validity_period_type' => $weeks, 'validity_period_value' => 5, 'price' => 150.00],

            // --- Abonament bez limitu (miesiac kalendarzowy) ---
            ['name' => 'Bez limitu — miesięczny', 'mode' => MembershipMode::Unlimited, 'sessions_per_week' => null, 'entry_count' => null, 'validity_period_type' => $month, 'validity_period_value' => 1, 'price' => 250.00],

            // --- Wejscia pojedyncze / doplaty ---
            ['name' => 'Wejście jednorazowe', 'mode' => MembershipMode::SingleEntry, 'sessions_per_week' => null, 'entry_count' => 1, 'validity_period_type' => null, 'validity_period_value' => null, 'price' => 40.00],
            ['name' => 'Dodatkowy trening (przy aktywnym abonamencie)', 'mode' => MembershipMode::SingleEntry, 'sessions_per_week' => null, 'entry_count' => 1, 'validity_period_type' => null, 'validity_period_value' => null, 'price' => 20.00],
        ];

        foreach ($types as $type) {
            MembershipType::updateOrCreate(
                ['name' => $type['name']],
                $type,
            );
        }
    }
}
