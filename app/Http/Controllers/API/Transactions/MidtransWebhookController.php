<?php

namespace App\Http\Controllers\API\Transactions;

use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enum\Status\OrderStatusEnum;
use App\Http\Controllers\Controller;
use App\Enum\Status\MidtransStatusEnum;
use App\Models\ETicket;

class MidtransWebhookController extends Controller
{
    /**
     * Menangani notifikasi HTTP dari server Midtrans.
     */
    public function handle(Request $request)
    {
        try {
            // 1. Validasi Signature Key (Sangat Penting untuk Keamanan)
            $notification = new \Midtrans\Notification();

            $orderId = $notification->order_id;
            $transactionStatus = $notification->transaction_status;
            $fraudStatus = $notification->fraud_status ?? null;

            // Validasi hash
            $signatureKey = hash('sha512', $orderId . $notification->status_code . $notification->gross_amount . config('midtrans.server_key'));
            if ($signatureKey !== $notification->signature_key) {
                Log::warning('Midtrans Webhook: Invalid signature.', ['order_id' => $orderId]);
                return response()->json(['message' => 'Invalid signature'], 403);
            }

            // 2. Dapatkan status internal dari status Midtrans
            $orderStatus = MidtransStatusEnum::getOrderStatus($transactionStatus, $fraudStatus);

            // 3. Update status pesanan jika ada status baru yang relevan
            if ($orderStatus) {
                $this->updateOrderStatus($orderId, $orderStatus, $transactionStatus);
            }

            return response()->json(['message' => 'Notification processed successfully.']);

        } catch (\Exception $e) {
            Log::error('Midtrans webhook processing error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_body' => $request->all(),
            ]);
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Mengupdate status pesanan dan menjalankan aksi terkait (misal: buat e-ticket).
     */
    protected function updateOrderStatus(string $orderId, OrderStatusEnum $orderStatus, string $midtransStatus): void
    {
        DB::transaction(function () use ($orderId, $orderStatus, $midtransStatus) {
            // --- PERBAIKAN 2: Pastikan pencarian menggunakan kolom 'id' ---
            $order = Order::where('id', $orderId)->lockForUpdate()->first();

            if (!$order) {
                // Tambahkan log yang sangat jelas jika order tidak ditemukan
                Log::warning("Midtrans Webhook: Order with ID [{$orderId}] NOT FOUND in database. Please check if this UUID exists in your 'orders' table.");
                return;
            }

            if ($order->status === OrderStatusEnum::PAID) {
                Log::info("Midtrans Webhook: Order [{$orderId}] is already PAID. Ignoring notification.");
                return;
            }

            Log::info("Midtrans Webhook: Order [{$orderId}] - Updating order status from [{$order->status->value}] to [{$orderStatus->value}].");
            $order->update(['status' => $orderStatus->value]);

            // --- PERBAIKAN 1: Logika update status transaksi ---
            // Hanya update status transaksi jika pembayaran berhasil.
            if ($orderStatus === OrderStatusEnum::PAID) {
                $order->transactions()->where('status', '!=', 'settlement')->update(['status' => 'settlement']);

                Log::info("Midtrans Webhook: Order [{$orderId}] - Generating e-tickets...");
                $this->generateETicketsForOrder($order);
                Log::info("Midtrans Webhook: Order [{$orderId}] - E-tickets generated.");

                // $order->user->notify(new PaymentSuccessNotification($order));
            } elseif (in_array($orderStatus, [OrderStatusEnum::CANCELLED, OrderStatusEnum::EXPIRED])) {
                // Update status transaksi menjadi sama dengan status Midtrans
                $order->transactions()->update(['status' => $midtransStatus]);

                Log::info("Midtrans Webhook: Order [{$orderId}] - Restoring ticket stock.");
                foreach ($order->orderItems as $item) {
                    $item->ticket()->increment('stock', $item->quantity);
                }
            }
        });
    }

    /**
     * Membuat e-ticket untuk setiap item dalam pesanan yang sudah lunas.
     */
    protected function generateETicketsForOrder(Order $order): void
    {
        foreach ($order->orderItems as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                // Buat record e-ticket baru
                $order->eTickets()->create([
                    'user_id' => $order->user_id,
                    'ticket_id' => $item->ticket_id,
                    'ticket_code' => Str::upper('ZTX-' . Str::random(12)), // Generate kode unik
                    'attendee_name' => $order->user->name, // Default, bisa diubah nanti oleh user
                ]);
            }
        }
    }
}
