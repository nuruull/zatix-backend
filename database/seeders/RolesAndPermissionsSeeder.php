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

        $permissions = [
            'event.store',
            'event.update',
            'event.destroy',
            'facility.store',
            'facility.update',
            'facility.destroy',
            'ticket.store',
            'ticket.update',
            'ticket.destroy',
            'demo-request.index',
            'demo-request.store',
            'demo-request.update',
            'demo-request.current-step',
            'demo-request.submit-pitching-schedule',
            'demo-request.approve-pitching',
            'demo-request.demo-account',
            'demo-request.role-upgrade',
        ];

        foreach ($permissions as $permission_all) {
            Permission::create(['name' => $permission_all, 'guard_name' => 'api']);
        }

        $perm_super_admin = [
            'demo-request.index',
            'demo-request.update',
            'demo-request.approve-pitching',
            'demo-request.demo-account',
            'demo-request.role-upgrade',
        ];
        $perm_eo_owner = [
            'event.store',
            'event.update',
            'event.destroy',
            'facility.store',
            'facility.update',
            'facility.destroy',
            'ticket.store',
            'ticket.update',
            'ticket.destroy',
            'demo-request.store',
            'demo-request.submit-pitching-schedule',
        ];
        $perm_crew = [];
        $perm_finance = [];
        $perm_cashier = [];
        $perm_customer = [];

        $role_superadmin = Role::findByName('super-admin');
        foreach ($perm_super_admin as $permission_superadmin) {
            $role_superadmin->givePermissionTo($permission_superadmin);
        }
        $role_eoowner = Role::findByName('eo-owner');
        foreach ($perm_eo_owner as $permission_eoowner) {
            $role_eoowner->givePermissionTo($permission_eoowner);
        }
        $role_crew = Role::findByName('crew');
        foreach ($perm_crew as $permission_crew) {
            $role_crew->givePermissionTo($permission_crew);
        }
        $role_finance = Role::findByName('finance');
        foreach ($perm_finance as $permission_finance) {
            $role_finance->givePermissionTo($permission_finance);
        }
        $role_cashier = Role::findByName('cashier');
        foreach ($perm_cashier as $permission_cashier) {
            $role_cashier->givePermissionTo($permission_cashier);
        }
        $role_customer = Role::findByName('customer');
        foreach ($perm_customer as $permission_customer) {
            $role_customer->givePermissionTo($permission_customer);
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
