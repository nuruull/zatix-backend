<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = array(
            array(
                'name' => 'Super Admin',
                'email' => 'superadmin@zatix.com',
                'password' => 'admin123',
                'email_verified_at' => '2025-01-01 00:00:00',
            ),
            array(
                'name' => 'EO Owner',
                'email' => 'eoowner@zatix.com',
                'password' => 'eoowner123',
                'email_verified_at' => '2025-01-01 00:00:00',
            ),
            array(
                'name' => 'Crew',
                'email' => 'crew@zatix.com',
                'password' => 'crew123',
                'email_verified_at' => '2025-01-01 00:00:00',
            ),
            array(
                'name' => 'Finance',
                'email' => 'finance@zatix.com',
                'password' => 'finance123',
                'email_verified_at' => '2025-01-01 00:00:00',
            ),
            array(
                'name' => 'Cashier',
                'email' => 'cashier@zatix.com',
                'password' => 'cashier123',
                'email_verified_at' => '2025-01-01 00:00:00',
            ),
            array(
                'name' => 'Customer 1',
                'email' => 'customer1@zatix.com',
                'password' => 'customer123',
                'email_verified_at' => '2025-01-01 00:00:00',
            ),
            array(
                'name' => 'Customer 2',
                'email' => 'customer2@zatix.com',
                'password' => 'customer123',
                'email_verified_at' => '2025-01-01 00:00:00',
            ),
            array(
                'name' => 'Customer 3',
                'email' => 'customer3@zatix.com',
                'password' => 'customer123',
                'email_verified_at' => '2025-01-01 00:00:00',
            ),
        );

        foreach ($users as $user) {
            User::create([
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => Hash::make($user['password']),
                'email_verified_at' => $user['email_verified_at'],
            ]);
        }
    }
}
