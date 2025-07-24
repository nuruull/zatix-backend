<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;


class EventTestPerformanceSeeder extends Seeder
{
    public function run(): void
    {
        $usersToCreate = [];
        $csvData = [];
        $password = 'password123'; // Password sama untuk semua
        $roleId = 2;

        if (!Role::find($roleId)) {
            $this->command->error("Role dengan ID {$roleId} tidak ditemukan. Mohon periksa tabel roles.");
            return;
        }

        for ($i = 1; $i <= 100; $i++) {
            $email = "perftest_user_{$i}@test.com";
            $usersToCreate[] = [
                'name' => "Perf Test User {$i}",
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $csvData[] = ['email' => $email, 'password' => $password];
        }

        DB::transaction(function () use ($usersToCreate, $roleId) {
            User::where('email', 'like', 'perftest_user_%')->delete();
            // Spatie akan otomatis menghapus relasi role saat user dihapus

            User::insert($usersToCreate);

            // ----- BLOK KODE BARU UNTUK ROLE -----
            $this->command->info('Assigning roles to new users...');

            // Ambil semua user yang baru dibuat
            $newUsers = User::where('email', 'like', 'perftest_user_%')->get();

            $roleAssignments = [];
            foreach ($newUsers as $user) {
                $roleAssignments[] = [
                    'role_id' => $roleId,
                    'model_type' => User::class, // atau 'App\Models\User'
                    'model_id' => $user->id
                ];
            }
            if (!empty($roleAssignments)) {
                DB::table('model_has_roles')->insert($roleAssignments);
            }
            // ------------------------------------
        });

        $this->createCsv($csvData);

        $this->command->info('100 performance test users with "eo owner" role created successfully.');
        $this->command->info("CSV file generated at: " . database_path('seeders/akun_eo.csv'));
    }

    private function createCsv(array $data)
    {
        $filename = database_path('seeders/akun_eo.csv');
        $directory = dirname($filename);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $handle = fopen($filename, 'w');
        fputcsv($handle, ['email', 'password']);
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }
}
