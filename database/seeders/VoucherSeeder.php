<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class VoucherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('order_voucher')->truncate();
        DB::table('vouchers')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Ambil satu user admin untuk dijadikan pembuat voucher
        $adminUser = User::role('super-admin')->first();
        if (!$adminUser) {
            $this->command->error('Tidak ada user Super Admin ditemukan untuk membuat voucher. Lewati VoucherSeeder.');
            return;
        }

        Voucher::create([
            'user_id' => $adminUser->id,
            'name' => 'Diskon Peluncuran 25%',
            'code' => 'ZATIXLAUNCH',
            'discount_type' => 'percentage',
            'discount_value' => 25,
            'max_amount' => 50000, // Menggunakan nama kolom baru
            'usage_limit' => 100,
            'valid_until' => now()->addMonths(3),
            'is_active' => true,
        ]);

        Voucher::create([
            'user_id' => $adminUser->id,
            'name' => 'Potongan Langsung 15 Ribu',
            'code' => 'HEMAT15K',
            'discount_type' => 'fixed',
            'discount_value' => 15000,
            'max_amount' => 0,
            'usage_limit' => 200,
            'valid_until' => now()->addMonths(6),
            'is_active' => true,
        ]);
    }
}
