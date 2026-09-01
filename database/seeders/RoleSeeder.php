<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * The three roles from spec section 2 (Admin / Trener / Klient).
     * Permissions come later with the panels — initialization creates roles only.
     */
    public function run(): void
    {
        foreach (['admin', 'trainer', 'client'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
