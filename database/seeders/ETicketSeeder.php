<?php

namespace Database\Seeders;

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
            $eoKerenOwner = User::factory()->create(['name' => 'Owner EO Keren'])->assignRole('eo-owner');
            $eoKeren = EventOrganizer::factory()->create(['eo_owner_id' => $eoKerenOwner->id, 'name' => 'EO Keren']);
            $crewEoKeren = User::factory()->create([
                'name' => 'Crew EO Keren',
                'email' => 'crew.keren@zatix.id',
                'password' => bcrypt('password123')
            ])->assignRole('crew');
            $eoKeren->members()->attach($crewEoKeren->id);

            $eoPesaingOwner = User::factory()->create(['name' => 'Owner EO Pesaing'])->assignRole('eo-owner');
            $eoPesaing = EventOrganizer::factory()->create(['eo_owner_id' => $eoPesaingOwner->id, 'name' => 'EO Pesaing']);
            $crewEoPesaing = User::factory()->create([
                'name' => 'Crew EO Pesaing',
                'email' => 'crew.pesaing@zatix.id',
                'password' => bcrypt('password123')
            ])->assignRole('crew');
            $eoPesaing->members()->attach($crewEoPesaing->id);

            //create publish event
            $eventKeren = Event::factory()->create(['is_published' => true, 'eo_id' => $eoKeren->id]);

            $validOrder = Order::factory()->create([
                'event_id' => $eventKeren->id,
                'status' => OrderStatusEnum::PAID->value,
            ]);
            ETicket::factory()->create([
                'order_id' => $validOrder->id,
                'ticket_id' => $validOrder->orderItems->first()->ticket_id,
                'user_id' => $validOrder->user_id,
            ]);

            $usedOrder = Order::factory()->create([
                'event_id' => $eventKeren->id,
                'status' => OrderStatusEnum::PAID->value,
            ]);
            ETicket::factory()->create([
                'order_id' => $usedOrder->id,
                'ticket_id' => $usedOrder->orderItems->first()->ticket_id,
                'user_id' => $usedOrder->user_id,
                'checked_in_at' => now()->subHour(), // Sudah check-in 1 jam lalu
                'checked_in_by' => $crewEoKeren->id, // Di-scan oleh crew yang benar
            ]);

            $cancelledOrder = Order::factory()->create([
                'event_id' => $eventKeren->id,
                'status' => OrderStatusEnum::CANCELLED->value, // Status order tidak PAID
            ]);
            ETicket::factory()->create([
                'order_id' => $cancelledOrder->id,
                'ticket_id' => $cancelledOrder->orderItems->first()->ticket_id,
                'user_id' => $cancelledOrder->user_id,
            ]);

            //create unpublished event
            $unpublishedEvent = Event::factory()->create(['is_published' => false, 'eo_id' => $eoKeren->id]);
            $inactiveEventOrder = Order::factory()->create([
                'event_id' => $unpublishedEvent->id,
                'status' => OrderStatusEnum::PAID->value,
            ]);
            ETicket::factory()->create([
                'order_id' => $inactiveEventOrder->id,
                'ticket_id' => $inactiveEventOrder->orderItems->first()->ticket_id,
                'user_id' => $inactiveEventOrder->user_id,
            ]);
        });
    }
}
