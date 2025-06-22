<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Document;
use App\Models\EventOrganizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class EventOrganizerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        EventOrganizer::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $eoOwners = User::role('eo-owner')->get();

        if ($eoOwners->isEmpty()) {
            $this->command->error("Tidak ada user ditemukan. Harap buat beberapa user terlebih dahulu sebelum menjalankan seeder ini.");
            return;
        }

        // Data dummy untuk event organizer
        $organizers = [
            [
                'name' => 'Gempita Event Planner',
                'description' => 'Merencanakan dan melaksanakan event skala besar dengan presisi dan kreativitas. Berpengalaman lebih dari 10 tahun.',
                'email_eo' => 'kontak@gempita.com',
                'phone_no_eo' => '081234567890',
                'address_eo' => 'Jl. Merdeka No. 123, Jakarta',
            ],
        ];

        // Looping untuk membuat data
        foreach ($organizers as $organizerData) {
            EventOrganizer::create([
                'name' => $organizerData['name'],
                'logo' => null,
                'description' => $organizerData['description'],
                'email_eo' => $organizerData['email_eo'],
                'phone_no_eo' => $organizerData['phone_no_eo'],
                'address_eo' => $organizerData['address_eo'],
                'eo_owner_id' => $eoOwners->id,
            ]);
        }
    }
}
