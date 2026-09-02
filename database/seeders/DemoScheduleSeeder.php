<?php

namespace Database\Seeders;

use App\Domain\Scheduling\Actions\GenerateMonthlySchedule;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Wzorzec tygodniowy + wygenerowany harmonogram na wrzesień 2026 — dane demo pod
 * plik test-data (testowi_klienci.json). NIE jest podpięty pod DatabaseSeeder;
 * uruchamiany ręcznie: php artisan db:seed --class=DemoScheduleSeeder
 *
 * Slot = (dzień ISO, godzina, typ zajęć) — dokładnie te, których używa test-data:seed.
 */
class DemoScheduleSeeder extends Seeder
{
    private const MONTH = '2026-09-01';

    /** @var list<array{0:int,1:string,2:string}> [weekday ISO, HH:MM, nazwa typu] */
    private const SLOTS = [
        [1, '17:55', 'Funkcjonal Choreo Step'],
        [1, '19:00', 'TBC Max'],
        [1, '20:05', 'HIIT'],
        [2, '17:55', 'Body Pump'],
        [2, '19:00', 'TBC'],
        [2, '20:05', 'Fit Dance'],
        [3, '17:55', 'HIIT'],
        [3, '19:00', 'Body Pump'],
        [3, '20:05', 'TBC Max'],
        [4, '17:55', 'TBC'],
        [4, '19:00', 'Fit Dance'],
        [4, '20:05', 'Body Pump'],
        [5, '06:00', 'Mix Treningowy'],
    ];

    public function run(): void
    {
        $month = CarbonImmutable::parse(self::MONTH)->startOfMonth();

        if (ClassGroup::query()->activeForMonth($month)->exists()) {
            $this->command?->warn('DemoScheduleSeeder: wzorzec na wrzesień 2026 już istnieje — pomijam.');

            return;
        }

        $types = ClassType::query()->pluck('id', 'name');

        foreach (self::SLOTS as [$weekday, $time, $typeName]) {
            $typeId = $types[$typeName] ?? null;
            if ($typeId === null) {
                $this->command?->warn("DemoScheduleSeeder: brak typu zajęć {$typeName} — pomijam slot.");

                continue;
            }

            ClassGroup::create([
                'class_type_id' => $typeId,
                'trainer_id' => null,
                'weekday' => $weekday,
                'start_time' => $time,
                'duration_minutes' => 55,
                'capacity' => 20,
                'active_from' => $month->toDateString(),
                'active_to' => $month->toDateString(),
            ]);
        }

        app(GenerateMonthlySchedule::class)->handle($month);

        $this->command?->info('DemoScheduleSeeder: wzorzec + harmonogram na wrzesień 2026 gotowe.');
    }
}
