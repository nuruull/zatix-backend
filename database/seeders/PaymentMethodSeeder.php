<?php

namespace Database\Seeders;

use DB;
use App\Models\Bank;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;
use App\Models\PaymentMethodCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Kosongkan tabel untuk menghindari duplikasi
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        PaymentMethod::truncate();
        PaymentMethodCategory::truncate();
        Bank::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. Buat Kategori Metode Pembayaran
        $vaCategory = PaymentMethodCategory::create(['name' => 'Virtual Account', 'is_active' => true]);
        $ewalletCategory = PaymentMethodCategory::create(['name' => 'E-Wallet', 'is_active' => true]);
        $qrisCategory = PaymentMethodCategory::create(['name' => 'QRIS', 'is_active' => true]);

        // 2. Buat Data Bank / Penyedia Layanan
        $bca = Bank::create(['name' => 'BCA', 'code' => 'bca', 'type' => 'bank_transfer', 'main_image' => 'images/banks/bca.png']);
        $mandiri = Bank::create(['name' => 'Mandiri', 'code' => 'mandiri', 'type' => 'echannel', 'main_image' => 'images/banks/mandiri.png']);
        $bni = Bank::create(['name' => 'BNI', 'code' => 'bni', 'type' => 'bank_transfer', 'main_image' => 'images/banks/bni.png']);
        $gopay = Bank::create(['name' => 'GoPay', 'code' => 'gopay', 'type' => 'gopay', 'main_image' => 'images/banks/gopay.png']);
        $qris = Bank::create(['name' => 'QRIS', 'code' => 'qris', 'type' => 'qris', 'main_image' => 'images/banks/qris.png']);

        // 3. Hubungkan Bank ke Kategori untuk membuat Metode Pembayaran yang aktif
        PaymentMethod::create([
            'payment_method_category_id' => $vaCategory->id,
            'bank_id' => $bca->id,
            'is_active' => true,
            'is_maintenance' => false,
            'priority' => 1,
        ]);
        PaymentMethod::create([
            'payment_method_category_id' => $vaCategory->id,
            'bank_id' => $mandiri->id,
            'is_active' => true,
            'is_maintenance' => false,
            'priority' => 2,
        ]);
        PaymentMethod::create([
            'payment_method_category_id' => $vaCategory->id,
            'bank_id' => $bni->id,
            'is_active' => true,
            'is_maintenance' => false,
            'priority' => 3,
        ]);
        PaymentMethod::create([
            'payment_method_category_id' => $ewalletCategory->id,
            'bank_id' => $gopay->id,
            'is_active' => true,
            'is_maintenance' => false,
            'priority' => 4,
        ]);
        PaymentMethod::create([
            'payment_method_category_id' => $qrisCategory->id,
            'bank_id' => $qris->id,
            'is_active' => true,
            'is_maintenance' => false,
            'priority' => 5,
        ]);

    }
}
