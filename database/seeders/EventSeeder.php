<?php

namespace Database\Seeders;

use Carbon\Carbon;
use App\Models\Event;
use App\Models\Facility;
use App\Models\TermAndCon;
use App\Models\TicketType;
use App\Enum\Type\TncTypeEnum;
use App\Models\EventOrganizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('tickets')->truncate();
        DB::table('event_facilities')->truncate();
        Event::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        Storage::disk('public')->deleteDirectory('event_posters');
        Storage::disk('public')->makeDirectory('event_posters');

        $organizer = EventOrganizer::first();
        if (!$organizer) {
            $this->command->error('Tidak ada data Event Organizer. Harap jalankan EventOrganizerSeeder terlebih dahulu.');
            return;
        }

        $tnc = TermAndCon::where('type', TncTypeEnum::EVENT)->first();
        if (!$tnc) {
            $this->command->error('Tidak ada data Terms & Conditions untuk Event. Harap buat data T&C terlebih dahulu.');
            return;
        }

        $facilities = Facility::take(3)->pluck('id');
        $ticketType = TicketType::first();
        if (!$ticketType) {
            $this->command->error('Tidak ada data Tipe Tiket. Harap buat data tipe tiket terlebih dahulu.');
            return;
        }

        $eventsData = [
            [
                'name' => 'Workshop Fotografi: Teknik Dasar',
                'poster' => 'Workshop-Fotografi.jpg',
                'description' => 'Belajar teknik dasar fotografi dari para profesional. Event ini masih dalam bentuk draf.',
                'start_date' => Carbon::now()->addWeeks(2),
                'end_date' => Carbon::now()->addWeeks(2),
                'status' => 'active',
                'is_published' => true,
                'is_public' => true,
            ],
            [
                'name' => 'Konser Amal Kemerdekaan 2025',
                'poster' => 'konser-amal.jpg',
                'description' => 'Konser musik untuk menggalang dana bagi para veteran. Terbuka untuk umum.',
                'start_date' => Carbon::now()->addMonth(1),
                'end_date' => Carbon::now()->addMonth(1),
                'status' => 'active',
                'is_published' => true,
                'is_public' => true, // <-- Event ini publik
            ],
            [
                'name' => 'Private Gathering: Alumni Angkatan 2010',
                'poster' => 'private-event.jpeg',
                'description' => 'Acara kumpul-kumpul khusus untuk alumni angkatan 2010. Akses hanya melalui link undangan.',
                'start_date' => Carbon::now()->addMonths(2),
                'end_date' => Carbon::now()->addMonths(2),
                'status' => 'active',
                'is_published' => true,
                'is_public' => false,
            ],
            [
                'name' => 'Konser BTS: Comeback From Military',
                'poster' => 'poster-konserjpeg.jpeg',
                'description' => 'BTS mengadakan konser untuk army setelah kepulangan 7 member dari wamil',
                'start_date' => Carbon::now()->addMonths(2),
                'end_date' => Carbon::now()->addMonths(2),
                'status' => 'active',
                'is_published' => true,
                'is_public' => true,
            ],
            [
                'name' => 'Workshop Flower Bouqet',
                'poster' => 'workshop-bunga.jpg',
                'description' => 'Pada workshop ini mengajak wanita-wanita indonesia mempelajari cara membuat buket bunga',
                'start_date' => Carbon::now()->addMonths(2),
                'end_date' => Carbon::now()->addMonths(2),
                'status' => 'draft',
                'is_published' => false,
                'is_public' => false,
            ],
            [
                'name' => 'Konser TXT: Eternally',
                'poster' => 'konser-txt.jpg',
                'description' => 'Konser TXT diadakan di Indonesia',
                'start_date' => Carbon::now()->addMonths(2),
                'end_date' => Carbon::now()->addMonths(2),
                'status' => 'archive',
                'is_published' => false,
                'is_public' => false,
            ],
        ];

        foreach ($eventsData as $data) {
            try {
                $sourcePath = database_path('seeders/images/event_posters/' . $data['poster']);
                $destinationPath = 'event_posters/' . $data['poster'];

                Storage::disk('public')->put($destinationPath, file_get_contents($sourcePath));

                // Buat event utama
                $event = $organizer->events()->create([
                    'name' => $data['name'],
                    'poster' => $destinationPath,
                    'description' => $data['description'],
                    'start_date' => $data['start_date']->toDateString(),
                    'start_time' => '19:00',
                    'end_date' => $data['end_date']->toDateString(),
                    'end_time' => '23:00',
                    'location' => 'Gedung Serbaguna Kota Fiktif',
                    'contact_phone' => $organizer->phone_no_eo,
                    'tnc_id' => $tnc->id,
                    'status' => $data['status'],
                    'is_published' => $data['is_published'],
                    'is_public' => $data['is_public'],
                ]);

                // Tambahkan fasilitas ke event
                if ($facilities->isNotEmpty()) {
                    $event->facilities()->sync($facilities->toArray());
                }

                // Buat satu jenis tiket untuk setiap event
                $event->tickets()->create([
                    'name' => 'Tiket Regular',
                    'price' => 150000,
                    'stock' => 500,
                    'limit' => 5,
                    'start_date' => Carbon::now(),
                    'end_date' => $data['start_date'],
                    'ticket_type_id' => $ticketType->id,
                ]);
            } catch (\Exception $e) {
                $this->command->error("Gagal menyalin gambar atau membuat event: " . $data['name'] . ". Error: " . $e->getMessage());
            }
        }
    }
}
