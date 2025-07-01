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
                $this->updateOrderStatus($orderId, $orderStatus);
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
    protected function updateOrderStatus(string $orderId, OrderStatusEnum $orderStatus): void
    {
        // Gunakan transaksi untuk memastikan semua operasi berhasil atau tidak sama sekali
        DB::transaction(function () use ($orderId, $orderStatus) {
            // Lock baris untuk mencegah race condition saat notifikasi datang hampir bersamaan
            $order = Order::where('id', $orderId)->lockForUpdate()->first();

            // Hanya proses jika order ada dan statusnya belum lunas (mencegah proses ganda)
            if ($order && $order->status !== OrderStatusEnum::PAID->value) {

                $order->update(['status' => $orderStatus->value]);

                // Update juga status di tabel 'transactions'
                $order->transactions()->where('status', '!=', 'settlement')->update(['status' => 'settlement']);

                // Jika pembayaran berhasil (LUNAS)
                if ($orderStatus === OrderStatusEnum::PAID) {
                    $this->generateETicketsForOrder($order);
                    // Kirim notifikasi email ke user bahwa pembayaran berhasil
                    // $order->user->notify(new PaymentSuccessNotification($order));
                }
                // Jika pesanan dibatalkan atau kadaluarsa
                elseif (in_array($orderStatus, [OrderStatusEnum::CANCELLED, OrderStatusEnum::EXPIRED])) {
                    // Kembalikan stok tiket
                    foreach ($order->orderItems as $item) {
                        $item->ticket()->increment('stock', $item->quantity);
                    }
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
