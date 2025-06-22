<?php

namespace Database\Seeders;

use Faker\Factory as Faker;
use App\Models\EventOrganizer;
use Illuminate\Database\Seeder;
use App\Enum\Type\OrganizerTypeEnum;
use App\Enum\Status\DocumentStatusEnum;

class DocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $eventOrganizers = EventOrganizer::with('eo_owner')->get();

        if ($eventOrganizers->isEmpty()) {
            $this->command->warn("Tidak ada Event Organizer untuk diberi dokumen. Lewati DocumentSeeder.");
            return;
        }

        $allStatuses = DocumentStatusEnum::cases();
        $docCount = 0;

        foreach ($eventOrganizers as $eo) {
            // Tentukan dokumen yang akan dibuat berdasarkan tipe EO
            if ($eo->organizer_type === OrganizerTypeEnum::INDIVIDUAL) {
                // Untuk individual, KTP wajib
                $eo->documents()->create([
                    'type'    => 'ktp',
                    'file'    => 'documents/dummy/ktp.pdf',
                    'number'  => $faker->nik(),
                    'name'    => $eo->eo_owner->name,
                    'address' => $eo->address_eo,
                    'status'  => DocumentStatusEnum::VERIFIED,
                ]);
                $docCount++;
            } else {
                // Untuk company, NPWP dan NIB wajib
                $eo->documents()->create([
                    'type'    => 'npwp',
                    'file'    => 'documents/dummy/npwp.pdf',
                    'number'  => $faker->numerify('##.###.###.#-###.###'),
                    'name'    => $eo->name,
                    'address' => $eo->address_eo,
                    'status'  => DocumentStatusEnum::VERIFIED,
                ]);
                $docCount++;

                $eo->documents()->create([
                    'type'    => 'nib',
                    'file'    => 'documents/dummy/nib.pdf',
                    'number'  => $faker->numerify('##############'),
                    'name'    => $eo->name,
                    'address' => $eo->address_eo,
                    'status'  => DocumentStatusEnum::VERIFIED,
                ]);
                $docCount++;
            }
        }
    }
}
