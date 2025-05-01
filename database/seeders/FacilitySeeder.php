<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FacilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Facility::insert([
            ['name' => 'Toilet', 'icon' => 'fa-solid fa-toilet'],
            ['name' => 'Konsumsi', 'icon' => 'fa-solid fa-utensils'],
            ['name' => 'Snack', 'icon' => 'fa-solid fa-cookie-bite'],
            ['name' => 'Tempat Parkir', 'icon' => 'fa-solid fa-square-parking'],
            ['name' => 'Wi-Fi', 'icon' => 'fa-solid fa-wifi'],
            ['name' => 'Masjid', 'icon' => 'fa-solid fa-mosque'],
            ['name' => 'Musholla', 'icon' => 'fa-solid fa-person-praying'],
            ['name' => 'Ambulance', 'icon' => 'fa-solid fa-truck-medical'],
            ['name' => 'P3K', 'icon' => 'fa-solid fa-suitcase-medical'],
            ['name' => 'Area VIP', 'icon' => 'fa-solid fa-star'],
            ['name' => 'Booth Tenant', 'icon' => 'fa-solid fa-store'],
            ['name' => 'Tribun', 'icon' => 'fa-solid fa-chair'],
            ['name' => 'Tenda', 'icon' => 'fa-solid fa-tent'],
            ['name' => 'Aksesibilitas Disabilitas', 'icon' => 'fa-solid fa-wheelchair'],
            ['name' => 'Backstage', 'icon' => 'fa-solid fa-door-closed'],
            ['name' => 'Photographer', 'icon' => 'fa-solid fa-camera'],
            ['name' => 'Smoking Area', 'icon' => 'fa-solid fa-smoking'],
            ['name' => 'Charging Station', 'icon' => 'fa-solid fa-bolt'],
            ['name' => 'Ruang Laktasi', 'icon' => 'fa-solid fa-person-breastfeeding'],
            ['name' => 'Kids Corner', 'icon' => 'fa-solid fa-children'],
            ['name' => 'Pusat Informasi', 'icon' => 'fa-solid fa-circle-info'],
            ['name' => 'Helpdesk', 'icon' => 'fa-solid fa-headset'],
        ]);

    }
}
