<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Notifications\ETicketsGenerated;
use App\Enum\Type\FinancialTransactionTypeEnum;

class ProcessPaidOrder
{
    public function execute(Order $order, ?User $recorder = null): void
    {
        // Bagian 1: Membuat E-Tickets untuk setiap item yang dibeli
        // Pastikan relasi yang dibutuhkan sudah di-load untuk efisiensi
        $order->loadMissing('orderItems.ticket', 'user');

        foreach ($order->orderItems as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                $order->eTickets()->create([
                    'user_id' => $order->user_id,
                    'ticket_id' => $item->ticket_id,
                    'ticket_code' => Str::upper('ZTX-' . Str::random(12)),
                    'attendee_name' => $order->user->name,
                ]);
            }
        }

        // Bagian 2: Mencatat pemasukan ke dalam transaksi keuangan
        $order->loadMissing('event.eventOrganizer');

        // Tentukan siapa yang mencatat dan apa kategorinya
        $recorderId = $recorder ? $recorder->id : $order->event->eventOrganizer->eo_owner_id;
        $category = $recorder ? 'Offline Ticket Sales' : 'Online Ticket Sales';

        // Gunakan relasi dari event untuk membuat transaksi keuangan baru
        $order->event->financialTransactions()->create([
            'order_id' => $order->id, // Tautkan ke order spesifik
            'type' => FinancialTransactionTypeEnum::INCOME,
            'category' => $category,
            'description' => 'Pendapatan dari Order #' . $order->id,
            'amount' => $order->net_amount,
            'transaction_date' => now()->format('Y-m-d'),
            'recorded_by_user_id' => $recorderId,
        ]);

        Log::info("Financial income recorded for order [{$order->id}].");

        if (is_null($recorder)) {
            $order->user->notify(new ETicketsGenerated($order));
            Log::info("Notification for e-tickets dispatched for order [{$order->id}].");
        }
    }
}
