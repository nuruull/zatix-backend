<?php

namespace Database\Seeders;

use App\Models\TicketType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TicketTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TicketType::insert([
            ['name' => 'VIP', 'description' => 'Tiket premium dengan fasilitas tambahan.'],
            ['name' => 'Regular', 'description' => 'Tiket biasa untuk umum.'],
            ['name' => 'Early Bird', 'description' => 'Tiket dengan harga diskon untuk pembelian awal.'],
            ['name' => 'Student', 'description' => 'Tiket dengan harga khusus untuk pelajar.'],
            ['name' => 'Presale', 'description' => 'Tiket yang dijual sebelum acara dengan harga khusus.'],
            ['name' => 'Group', 'description' => 'Tiket untuk kelompok dengan diskon khusus.'],
            ['name' => 'Couple', 'description' => 'Tiket untuk pasangan atau untuk 2 orang dengan diskon khusus.'],
            ['name' => 'Standard', 'description' => 'Tiket standar tanpa fasilitas tambahan.'],
            ['name' => 'Season Pass', 'description' => 'Tiket yang memberikan akses ke beberapa acara atau sesi.'],
            ['name' => 'Sponsor', 'description' => 'Tiket khusus untuk sponsor dengan berbagai fasilitas dan akses istimewa.'],
            ['name' => 'Press', 'description' => 'Tiket khusus untuk wartawan/media.'],
        ]);
    }
}
