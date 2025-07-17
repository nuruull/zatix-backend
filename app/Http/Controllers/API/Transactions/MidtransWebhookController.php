<?php

namespace App\Http\Controllers\API\Transactions;

use App\Models\Order;
use App\Models\ETicket;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enum\Status\OrderStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use App\Enum\Status\MidtransStatusEnum;
use App\Notifications\ETicketsGenerated;
use App\Enum\Type\FinancialTransactionTypeEnum;
use App\Actions\ProcessPaidOrder;

class MidtransWebhookController extends Controller
{
    /**
     * Menangani notifikasi HTTP dari server Midtrans.
     */
    public function handle(Request $request)
    {
        try {
            $payload = $request->all();

            // 1. Validasi Signature Key (Sangat Penting untuk Keamanan)
            $orderId = $payload['order_id'];
            $statusCode = $payload['status_code'];
            $grossAmount = $payload['gross_amount'];
            $serverKey = config('midtrans.server_key');

            $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

            if ($signatureKey !== $payload['signature_key']) {
                Log::warning('Midtrans Webhook: Invalid signature.', ['order_id' => $orderId]);
                return response()->json(['message' => 'Invalid signature'], 403);
            }

            // 2. Dapatkan status internal dari status Midtrans
            $transactionStatus = $payload['transaction_status'];
            $fraudStatus = $payload['fraud_status'] ?? null;
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
            $order = Order::with('user', 'orderItems.ticket')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$order || $order->status === OrderStatusEnum::PAID) {
                // Log jika tidak ditemukan atau sudah lunas, lalu hentikan proses.
                $logMessage = !$order ? "Order with ID [{$orderId}] NOT FOUND." : "Order [{$orderId}] is already PAID. Ignoring.";
                Log::info("Midtrans Webhook: " . $logMessage);
                return;
            }

            Log::info("Midtrans Webhook: Order [{$orderId}] - Updating status to [{$orderStatus->value}].");
            $order->update(['status' => $orderStatus->value]);

            if ($orderStatus === OrderStatusEnum::PAID) {
                $order->transactions()->where('status', '!=', 'settlement')->update(['status' => 'settlement']);

                // 2. Ganti logika duplikat dengan memanggil Action Class
                (new ProcessPaidOrder)->execute($order);
                Log::info("Midtrans Webhook: Order [{$orderId}] - Paid order processing delegated to action.");

                // Notifikasi tetap di sini karena spesifik untuk alur online
                $order->user->notify(new ETicketsGenerated($order));
                Log::info("Midtrans Webhook: Order [{$orderId}] - Notification job dispatched.");

            } elseif (in_array($orderStatus, [OrderStatusEnum::CANCELLED, OrderStatusEnum::EXPIRED])) {
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
                $order->eTickets()->create([
                    'user_id' => $order->user_id,
                    'ticket_id' => $item->ticket_id,
                    'ticket_code' => Str::upper('ZTX-' . Str::random(12)),
                    'attendee_name' => $order->user->name,
                ]);
            }
        }
    }

    /**
     * Mencatat pendapatan dari penjualan tiket sebagai 'income' di tabel keuangan.
     */
    protected function recordTicketSalesAsIncome(Order $order): void
    {
        FinancialTransaction::create([
            'event_id' => $order->event_id,
            'type' => FinancialTransactionTypeEnum::INCOME, // Jenis transaksi adalah pemasukan
            'category' => 'Ticket Sales', // Kategori spesifik untuk pelaporan
            'description' => 'Pendapatan dari Order #' . $order->id,
            'amount' => $order->net_amount, // Ambil jumlah bersih yang dibayar customer
            'transaction_date' => now(), // Tanggal saat pembayaran dikonfirmasi
            'recorded_by_user_id' => $order->event->eventOrganizer->eo_owner_id,
        ]);
    }
}
