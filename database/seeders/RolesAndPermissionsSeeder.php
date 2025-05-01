<?php

namespace Database\Seeders;

use App\Models\User;
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
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'super-admin',
            'eo-owner',
            'crew',
            'finance',
            'cashier',
            'customer',

        ];

        foreach ($roles as $role) {
            Role::create(['name' => $role, 'guard_name' => 'api']);
        }

        $permissions = [];

        foreach ($permissions as $permission_all) {
            Permission::create(['name' => $permission_all, 'guard_name' => 'api']);
        }

        $user = User::find(1);
        if ($user) {
            $user->assignRole('super-admin');
        }
        $user = User::find(2);
        if ($user) {
            $user->assignRole('eo-owner');
        }
        $user = User::find(3);
        if ($user) {
            $user->assignRole('crew');
        }
        $user = User::find(4);
        if ($user) {
            $user->assignRole('finance');
        }
        $user = User::find(5);
        if ($user) {
            $user->assignRole('cashier');
        }
        $user = User::find(6);
        if ($user) {
            $user->assignRole('customer');
        }
        $user = User::find(7);
        if ($user) {
            $user->assignRole('customer');
        }
        $user = User::find(8);
        if ($user) {
            $user->assignRole('customer');
        }
    }
}
