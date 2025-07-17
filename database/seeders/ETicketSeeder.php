<?php

namespace Database\Seeders;

use App\Enum\Status\EventStatusEnum;
use App\Models\User;
use App\Models\Event;
use App\Models\Order;
use App\Models\ETicket;
use App\Models\EventOrganizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Enum\Status\OrderStatusEnum;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ETicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // PENGGUNA YANG DIKETAHUI
            // ========================
            // 1. Buat satu customer yang jelas untuk digunakan di semua order
            $customerUser = User::factory()->create([
                'name' => 'Customer A',
                'email' => 'customer.a@zatix.id',
                'password' => bcrypt('password123'),
            ])->assignRole('customer');

            // 2. Buat EO dan Crew yang jelas
            $eoKerenOwner = User::factory()->create(['name' => 'Owner EO Keren'])->assignRole('eo-owner');
            $eoKeren = EventOrganizer::factory()->create(['eo_owner_id' => $eoKerenOwner->id, 'name' => 'EO Keren']);
            $crewEoKeren = User::factory()->create([
                'name' => 'Crew EO Keren',
                'email' => 'crew.keren@zatix.id',
                'password' => bcrypt('password123'),
            ])->assignRole('crew');
            $eoKeren->members()->attach($crewEoKeren->id);

            $eoPesaingOwner = User::factory()->create(['name' => 'Owner EO Pesaing'])->assignRole('eo-owner');
            $eoPesaing = EventOrganizer::factory()->create(['eo_owner_id' => $eoPesaingOwner->id, 'name' => 'EO Pesaing']);
            $crewEoPesaing = User::factory()->create([
                'name' => 'Crew EO Pesaing',
                'email' => 'crew.pesaing@zatix.id',
                'password' => bcrypt('password123'),
            ])->assignRole('crew');
            $eoPesaing->members()->attach($crewEoPesaing->id);


            // SKENARIO UNTUK EVENT "EO KEREN"
            // ===============================
            $eventKeren = Event::factory()->create(['is_published' => true, 'eo_id' => $eoKeren->id, 'name' => 'Konser Megah EO Keren', 'status' => EventStatusEnum::ACTIVE->value]);

            // Skenario 1: Tiket valid, belum dipakai
            $validOrder = Order::factory()->create([
                'event_id' => $eventKeren->id,
                'status' => OrderStatusEnum::PAID->value,
                'user_id' => $customerUser->id, // Gunakan customer yang sudah kita buat
            ]);
            ETicket::factory()->create([
                'order_id' => $validOrder->id,
                'ticket_id' => $validOrder->orderItems->first()->ticket_id,
                'user_id' => $validOrder->user_id,
                'attendee_name' => $customerUser->name,
            ]);

            // Skenario 2: Tiket sudah terpakai
            $usedOrder = Order::factory()->create([
                'event_id' => $eventKeren->id,
                'status' => OrderStatusEnum::PAID->value,
                'user_id' => $customerUser->id,
            ]);
            ETicket::factory()->create([
                'order_id' => $usedOrder->id,
                'ticket_id' => $usedOrder->orderItems->first()->ticket_id,
                'user_id' => $usedOrder->user_id,
                'checked_in_at' => now()->subHour(),
                'checked_in_by' => $crewEoKeren->id,
                'attendee_name' => $customerUser->name,
            ]);

            // Skenario 3: Tiket dari order yang dibatalkan
            $cancelledOrder = Order::factory()->create([
                'event_id' => $eventKeren->id,
                'status' => OrderStatusEnum::CANCELLED->value,
                'user_id' => $customerUser->id,
            ]);
            ETicket::factory()->create([
                'order_id' => $cancelledOrder->id,
                'ticket_id' => $cancelledOrder->orderItems->first()->ticket_id,
                'user_id' => $cancelledOrder->user_id,
                'attendee_name' => $customerUser->name,
            ]);

            // Skenario 4: Tiket untuk event yang belum di-publish
            $unpublishedEvent = Event::factory()->create(['is_published' => false, 'eo_id' => $eoKeren->id, 'name' => 'Event Rahasia EO Keren']);
            $inactiveEventOrder = Order::factory()->create([
                'event_id' => $unpublishedEvent->id,
                'status' => OrderStatusEnum::PAID->value,
                'user_id' => $customerUser->id,
            ]);
            ETicket::factory()->create([
                'order_id' => $inactiveEventOrder->id,
                'ticket_id' => $inactiveEventOrder->orderItems->first()->ticket_id,
                'user_id' => $inactiveEventOrder->user_id,
                'attendee_name' => $customerUser->name,
            ]);

            // SKENARIO TAMBAHAN UNTUK OTORISASI CREW
            // =========================================
            // Skenario 5: Tiket valid, tapi milik EO Pesaing
            $eventPesaing = Event::factory()->create(['is_published' => true, 'eo_id' => $eoPesaing->id, 'name' => 'Festival Musik EO Pesaing']);
            $orderPesaing = Order::factory()->create([
                'event_id' => $eventPesaing->id,
                'status' => OrderStatusEnum::PAID->value,
                'user_id' => $customerUser->id,
            ]);
            ETicket::factory()->create([
                'order_id' => $orderPesaing->id,
                'ticket_id' => $orderPesaing->orderItems->first()->ticket_id,
                'user_id' => $orderPesaing->user_id,
                'attendee_name' => $customerUser->name,
            ]);
        });
    }
}
