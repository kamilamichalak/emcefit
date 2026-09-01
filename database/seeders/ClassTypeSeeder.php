<?php

namespace Database\Seeders;

use App\Domain\Scheduling\Models\ClassType;
use Illuminate\Database\Seeder;

class ClassTypeSeeder extends Seeder
{
    /**
     * Slownik typow zajec klubu EMCEFIT (dane startowe). `required_equipment`
     * jest informacyjne — nie wplywa na logike rezerwacji.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Body Pump', 'description' => 'Trening siłowy ze sztangą do muzyki, angażuje wszystkie partie mięśni.', 'required_equipment' => 'sztanga, obciążenia, ławeczka'],
            ['name' => 'TBC', 'description' => 'Total Body Conditioning — ogólnorozwojowy trening całego ciała.', 'required_equipment' => 'hantle, guma oporowa'],
            ['name' => 'TBC Max', 'description' => 'Intensywniejsza odmiana TBC — większe obciążenia i tempo.', 'required_equipment' => 'hantle, guma oporowa'],
            ['name' => 'HIIT', 'description' => 'Trening interwałowy o wysokiej intensywności.', 'required_equipment' => null],
            ['name' => 'Fit Dance', 'description' => 'Taneczny trening cardio do muzyki.', 'required_equipment' => null],
            ['name' => 'Fit Dance Step', 'description' => 'Fit Dance z wykorzystaniem stepu.', 'required_equipment' => 'step'],
            ['name' => 'Funkcjonal Choreo Step', 'description' => 'Trening funkcjonalny z choreografią na stepie.', 'required_equipment' => 'step'],
            ['name' => 'Mix Treningowy', 'description' => 'Łączy elementy różnych zajęć — cardio, siła, mobilność.', 'required_equipment' => null],
        ];

        foreach ($types as $type) {
            ClassType::updateOrCreate(['name' => $type['name']], $type);
        }
    }
}
