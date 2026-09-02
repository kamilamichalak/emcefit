<?php

namespace Database\Seeders;

use App\Domain\Scheduling\Models\ClassType;
use Illuminate\Database\Seeder;

class ClassTypeSeeder extends Seeder
{
    /**
     * Slownik typow zajec klubu EMCEFIT (dane startowe). `required_equipment`
     * i `color` sa informacyjne (kolor sluzy do oznaczenia zajec na grafiku).
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Body Pump', 'description' => 'Trening siłowy ze sztangą do muzyki, angażuje wszystkie partie mięśni.', 'required_equipment' => 'sztanga, obciążenia, ławeczka', 'color' => '#E53935', 'icon' => 'Dumbbell', 'default_capacity' => 20],
            ['name' => 'TBC', 'description' => 'Total Body Conditioning — ogólnorozwojowy trening całego ciała.', 'required_equipment' => 'hantle, guma oporowa', 'color' => '#8E24AA', 'icon' => 'Activity', 'default_capacity' => 20],
            ['name' => 'TBC Max', 'description' => 'Intensywniejsza odmiana TBC — większe obciążenia i tempo.', 'required_equipment' => 'hantle, guma oporowa', 'color' => '#5E35B1', 'icon' => 'Flame', 'default_capacity' => 20],
            ['name' => 'HIIT', 'description' => 'Trening interwałowy o wysokiej intensywności.', 'required_equipment' => null, 'color' => '#F4511E', 'icon' => 'HeartPulse', 'default_capacity' => 20],
            ['name' => 'Fit Dance', 'description' => 'Taneczny trening cardio do muzyki.', 'required_equipment' => null, 'color' => '#EC407A', 'icon' => 'Music', 'default_capacity' => 25],
            ['name' => 'Fit Dance Step', 'description' => 'Fit Dance z wykorzystaniem stepu.', 'required_equipment' => 'step', 'color' => '#D81B60', 'icon' => 'Music2', 'default_capacity' => 20],
            ['name' => 'Funkcjonal Choreo Step', 'description' => 'Trening funkcjonalny z choreografią na stepie.', 'required_equipment' => 'step', 'color' => '#00897B', 'icon' => 'Footprints', 'default_capacity' => 18],
            ['name' => 'Mix Treningowy', 'description' => 'Łączy elementy różnych zajęć — cardio, siła, mobilność.', 'required_equipment' => null, 'color' => '#43A047', 'icon' => 'Repeat', 'default_capacity' => 20],
        ];

        foreach ($types as $type) {
            ClassType::updateOrCreate(['name' => $type['name']], $type);
        }
    }
}
