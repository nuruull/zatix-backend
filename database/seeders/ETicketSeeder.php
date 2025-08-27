<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Event;
use App\Models\Order;
use App\Models\ETicket;
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
        $this->command->info('Memulai E-Ticket Seeder...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        ETicket::truncate();
        DB::table('order_items')->truncate();
        Order::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        DB::transaction(function () {
            $this->command->info('Mencari data User dan Event yang relevan...');
            $customer1 = User::where('email', 'customer1@zatix.com')->firstOrFail();
            $customer2 = User::where('email', 'customer2@zatix.com')->firstOrFail();
            $crewChecker = User::where('email', 'crew@zatix.com')->firstOrFail();
            $mainEvent = Event::where('name', 'Workshop Fotografi: Teknik Dasar')->where('is_published', true)->firstOrFail();
            $unpublishedEvent = Event::where('name', 'Workshop Flower Bouqet')->where('is_published', false)->firstOrFail();
            $anotherEvent = Event::where('name', 'Konser BTS: Comeback From Military')->where('is_published', true)->firstOrFail();

            $this->command->info("Membuat tiket valid untuk '{$mainEvent->name}'...");
            $validOrder = Order::factory()->create([
                'event_id' => $mainEvent->id,
                'status' => OrderStatusEnum::PAID->value,
                'user_id' => $customer1->id,
            ]);
            ETicket::factory()->create([
                'order_id' => $validOrder->id,
                'ticket_id' => $validOrder->orderItems->first()->ticket_id,
                'user_id' => $validOrder->user_id,
                'attendee_name' => $customer1->name,
            ]);

            $this->command->info("Membuat tiket yang sudah terpakai untuk '{$mainEvent->name}'...");
            $usedOrder = Order::factory()->create([
                'event_id' => $mainEvent->id,
                'status' => OrderStatusEnum::PAID->value,
                'user_id' => $customer1->id,
            ]);
            ETicket::factory()->create([
                'order_id' => $usedOrder->id,
                'ticket_id' => $usedOrder->orderItems->first()->ticket_id,
                'user_id' => $usedOrder->user_id,
                'checked_in_at' => now()->subHour(),
                'checked_in_by' => $crewChecker->id,
                'attendee_name' => $customer1->name,
            ]);

            $this->command->info("Membuat tiket dari order yang dibatalkan untuk '{$mainEvent->name}'...");
            $cancelledOrder = Order::factory()->create([
                'event_id' => $mainEvent->id,
                'status' => OrderStatusEnum::CANCELLED->value,
                'user_id' => $customer2->id,
            ]);
            ETicket::factory()->create([
                'order_id' => $cancelledOrder->id,
                'ticket_id' => $cancelledOrder->orderItems->first()->ticket_id,
                'user_id' => $cancelledOrder->user_id,
                'attendee_name' => $customer2->name,
            ]);

            $this->command->info("Membuat tiket untuk event draft '{$unpublishedEvent->name}'...");
            $inactiveEventOrder = Order::factory()->create([
                'event_id' => $unpublishedEvent->id,
                'status' => OrderStatusEnum::PAID->value,
                'user_id' => $customer2->id,
            ]);
            ETicket::factory()->create([
                'order_id' => $inactiveEventOrder->id,
                'ticket_id' => $inactiveEventOrder->orderItems->first()->ticket_id,
                'user_id' => $inactiveEventOrder->user_id,
                'attendee_name' => $customer2->name,
            ]);

            $this->command->info("Membuat tiket valid untuk event lain '{$anotherEvent->name}'...");
            $orderLain = Order::factory()->create([
                'event_id' => $anotherEvent->id,
                'status' => OrderStatusEnum::PAID->value,
                'user_id' => $customer1->id,
            ]);
            ETicket::factory()->create([
                'order_id' => $orderLain->id,
                'ticket_id' => $orderLain->orderItems->first()->ticket_id,
                'user_id' => $orderLain->user_id,
                'attendee_name' => $customer1->name,
            ]);
        });

        $this->command->info('✅ E-Ticket Seeder selesai dijalankan.');
    }
}
