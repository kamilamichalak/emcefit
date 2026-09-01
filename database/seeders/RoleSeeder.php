<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Trzy role z sekcji 2 specyfikacji. Uprawnienia (permissions) dojdą
     * przy implementacji paneli — na etapie inicjalizacji tylko role.
     */
    public function run(): void
    {
        foreach (['admin', 'trener', 'klient'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
