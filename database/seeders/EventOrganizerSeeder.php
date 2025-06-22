<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\EventOrganizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Enum\Type\OrganizerTypeEnum;

class EventOrganizerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('documents')->delete();
        DB::table('event_organizers')->delete();

        $eoOwners = User::role('eo-owner')->get();
        if ($eoOwners->isEmpty()) {
            $this->command->error("Tidak ada user dengan role 'eo-owner'. Harap buat user terlebih dahulu.");
            return;
        }

        $organizerTypes = [OrganizerTypeEnum::INDIVIDUAL, OrganizerTypeEnum::COMPANY];
        $companyNames = ['Gempita Event Planner', 'Nusantara Creative', 'PestaRia Organizer', 'MICE Solutions', 'Jogja Expo Center'];

        foreach ($eoOwners as $index => $owner) {
            // Kita buat beberapa variasi
            $type = $organizerTypes[$index % count($organizerTypes)];

            $name = ($type === OrganizerTypeEnum::INDIVIDUAL)
                ? $owner->name . ' Organizer'
                : $companyNames[$index % count($companyNames)];

            EventOrganizer::create([
                'eo_owner_id' => $owner->id,
                'organizer_type' => $type,
                'name' => $name,
                'email_eo' => 'contact@' . strtolower(str_replace(' ', '', $name)) . '.com',
                'phone_no_eo' => '0812345678' . $index,
                'address_eo' => 'Jl. Pahlawan No. ' . ($index + 1) . ', Kota Fiktif',
                'description' => 'Deskripsi singkat untuk ' . $name,
            ]);
        }
    }
}
