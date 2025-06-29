<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\EventOrganizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $eventOrganizer = EventOrganizer::findOrFail(1);

            $staffsData = [
                [
                    'email' => 'finance@zatix.com',
                    'role' => 'finance',
                ],
                [
                    'email' => 'crew@zatix.com',
                    'role' => 'crew',
                ],
                [
                    'email' => 'cashier@zatix.com',
                    'role' => 'cashier',
                ],
            ];

            foreach ($staffsData as $staffData) {
                $staffUser = User::where('email', $staffData['email'])->firstOrFail();

                $role = Role::where('name', $staffData['role'])
                            ->where('guard_name', 'api')
                            ->firstOrFail();

                $staffUser->syncRoles($role);

                $eventOrganizer->members()->syncWithoutDetaching([
                    $staffUser->id => ['created_at' => now(), 'updated_at' => now()]
                ]);
            }
        });
    }
}
