<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            MembershipTypeSeeder::class,
            ClassTypeSeeder::class,
            TrainerSeeder::class,
        ]);

        // Bez User::factory() — faker jest zależnością dev, a ten seeder biega też
        // na produkcyjnym (demo) obrazie bez pakietów --dev. firstOrCreate = idempotentne.
        User::firstOrCreate(
            ['email' => 'admin@emcefit.test'],
            ['name' => 'Admin', 'password' => 'password'],
        )->assignRole('admin');
    }
}
