<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
class LoginTestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tentukan lokasi dan nama file CSV
        $csvPath = storage_path('app/seeders');
        $csvFile = $csvPath . '/login_users.csv';

        // Buat direktori jika belum ada
        if (!File::isDirectory($csvPath)) {
            File::makeDirectory($csvPath, 0755, true);
        }

        // Buka file CSV untuk ditulis
        $fileHandle = fopen($csvFile, 'w');

        // Tulis header ke file CSV
        fputcsv($fileHandle, ['email', 'password']);

        $usersForDb = [];
        $plainPassword = 'password123'; // Password yang akan ditulis di CSV
        $verifiedAt = now();

        $this->command->info('Mempersiapkan data untuk 1,000 pengguna...');

        // Loop untuk mempersiapkan data
        for ($i = 1; $i <= 1000; $i++) {
            $email = 'perftestuser' . $i . '@example.com';

            // 1. Tulis data (email & password mentah) ke file CSV
            fputcsv($fileHandle, [$email, $plainPassword]);

            // 2. Siapkan data untuk dimasukkan ke database (password di-hash)
            $usersForDb[] = [
                'name' => 'PerfTest User ' . $i,
                'email' => $email,
                'password' => Hash::make($plainPassword),
                'email_verified_at' => $verifiedAt,
                'created_at' => $verifiedAt,
                'updated_at' => $verifiedAt,
            ];
        }

        // Tutup file CSV
        fclose($fileHandle);
        $this->command->info('File CSV berhasil dibuat di: ' . $csvFile);

        // Masukkan semua data ke database
        $this->command->info('Memasukkan data ke database...');
        foreach (array_chunk($usersForDb, 500) as $chunk) {
            User::insert($chunk);
        }

        $this->command->info('Seeder untuk 1,000 pengguna terverifikasi selesai dijalankan.');
    }
}
