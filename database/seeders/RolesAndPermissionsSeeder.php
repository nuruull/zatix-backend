<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'super-admin',
            'eo-owner',
            'crew',
            'finance',
            'cashier',
            'customer',

        ];

        foreach ($roles as $role) {
            Role::create(['name' => $role]);
        }

        $permissions = [];

        foreach ($permissions as $permission_all) {
            Permission::create(['name' => $permission_all]);
        }
    }
}
