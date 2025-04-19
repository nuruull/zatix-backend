<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
                'password' => 'admin123'
            ),
            array(
                'name' => 'EO Owner',
                'email' => 'eoowner@zatix.com',
                'password' => 'eoowner123'
            ),
            array(
                'name' => 'Crew',
                'email' => 'crew@zatix.com',
                'password' => 'crew123'
            ),
            array(
                'name' => 'Finance',
                'email' => 'finance@zatix.com',
                'password' => 'finance123'
            ),
            array(
                'name' => 'Cashier',
                'email' => 'cashier@zatix.com',
                'password' => 'cashier123'
            ),
            array(
                'name' => 'Customer 1',
                'email' => 'customer1@zatix.com',
                'password' => 'customer123'
            ),
            array(
                'name' => 'Customer 2',
                'email' => 'customer2@zatix.com',
                'password' => 'customer123'
            ),
            array(
                'name' => 'Customer 3',
                'email' => 'customer3@zatix.com',
                'password' => 'customer123'
            ),
        );

        foreach ($users as $user) {
            User::create([
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => Hash::make($user['password'])
            ]);
        }
    }
}
