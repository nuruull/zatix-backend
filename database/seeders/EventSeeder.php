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
            // KONDISI 1: Masih DRAFT
            [
                'name' => 'Workshop Fotografi: Teknik Dasar (DRAFT)',
                'description' => 'Belajar teknik dasar fotografi dari para profesional. Event ini masih dalam bentuk draf.',
                'start_date' => Carbon::now()->addWeeks(2),
                'end_date' => Carbon::now()->addWeeks(2),
                'status' => 'draft',
                'is_published' => false,
                'is_public' => false, // Draf selalu tidak publik
            ],
            // KONDISI 2: Sudah PUBLISH dan bersifat PUBLIK
            [
                'name' => 'Konser Amal Kemerdekaan 2025',
                'description' => 'Konser musik untuk menggalang dana bagi para veteran. Terbuka untuk umum.',
                'start_date' => Carbon::now()->addMonth(1),
                'end_date' => Carbon::now()->addMonth(1),
                'status' => 'active',
                'is_published' => true,
                'is_public' => true, // <-- Event ini publik
            ],
            // KONDISI 3: Sudah PUBLISH tapi bersifat PRIVATE
            [
                'name' => 'Private Gathering: Alumni Angkatan 2010',
                'description' => 'Acara kumpul-kumpul khusus untuk alumni angkatan 2010. Akses hanya melalui link undangan.',
                'start_date' => Carbon::now()->addMonths(2),
                'end_date' => Carbon::now()->addMonths(2),
                'status' => 'active',
                'is_published' => true,
                'is_public' => false, // <-- Event ini privat
            ],
        ];

        foreach ($eventsData as $data) {
            // Buat event utama
            $event = $organizer->events()->create([
                'name' => $data['name'],
                'description' => $data['description'],
                'start_date' => $data['start_date']->toDateString(),
                'start_time' => '19:00:00',
                'end_date' => $data['end_date']->toDateString(),
                'end_time' => '23:00:00',
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
        }
    }
}
