<?php

namespace Database\Seeders;

use App\Domain\Trainers\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Seeder;

class TrainerSeeder extends Seeder
{
    /**
     * Jeden trener na start (spec sekcja 2) — zeby dalo sie przypisac go do
     * zajec we wzorcu grafiku. Konto: trener@emcefit.test / password.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'trener@emcefit.test'],
            ['name' => 'Trener Klubowy', 'password' => 'password'],
        );

        $user->assignRole('trainer');

        Trainer::firstOrCreate(
            ['user_id' => $user->id],
            ['specialization' => 'Zajęcia grupowe'],
        );
    }
}
