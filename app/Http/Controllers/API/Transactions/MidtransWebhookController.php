<?php

namespace App\Http\Controllers\API\Transactions;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enum\Status\OrderStatusEnum;
use App\Http\Controllers\Controller;
use App\Enum\Status\MidtransStatusEnum;
use App\Actions\ProcessPaidOrder;

class MidtransWebhookController extends Controller
{
    /**
     * Menangani notifikasi HTTP dari server Midtrans.
     */
    public function handle(Request $request, ProcessPaidOrder $processPaidOrderAction)
    {
        try {
            $payload = $request->all();

            // 1. Validasi Signature Key
            if (!$this->isSignatureKeyValid($payload)) {
                Log::warning('Midtrans Webhook: Invalid signature.', ['order_id' => $payload['order_id'] ?? null]);
                return response()->json(['message' => 'Invalid signature'], 403);
            }

            // 2. Dapatkan status internal
            $orderStatus = MidtransStatusEnum::getOrderStatus(
                $payload['transaction_status'],
                $payload['fraud_status'] ?? null
            );

            // 3. Update status pesanan jika relevan
            if ($orderStatus) {
                $this->updateOrderStatus(
                    $payload['order_id'],
                    $orderStatus,
                    $payload['transaction_status'],
                    $processPaidOrderAction // Kirim Action sebagai parameter
                );
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
    protected function updateOrderStatus(string $orderId, OrderStatusEnum $orderStatus, string $midtransStatus, ProcessPaidOrder $processPaidOrderAction): void
    {
        DB::transaction(function () use ($orderId, $orderStatus, $midtransStatus, $processPaidOrderAction) {
            $order = Order::with('user', 'orderItems.ticket')->where('id', $orderId)->lockForUpdate()->first();

            // Hentikan jika order tidak ada atau sudah pernah diproses sebagai PAID
            if (!$order || $order->status === OrderStatusEnum::PAID) {
                return;
            }

            $order->update(['status' => $orderStatus->value]);

            // Jika statusnya LUNAS, serahkan semua pekerjaan ke Action Class
            if ($orderStatus === OrderStatusEnum::PAID) {

                $processPaidOrderAction->execute($order); // PANGGIL ACTION DI SINI

                Log::info("Midtrans Webhook: Order [{$orderId}] - Paid order processing delegated to action.");

            } elseif (in_array($orderStatus, [OrderStatusEnum::CANCELLED, OrderStatusEnum::EXPIRED])) {
                // Logika untuk mengembalikan stok jika order batal/kedaluwarsa
                $order->transactions()->update(['status' => $midtransStatus]);
                foreach ($order->orderItems as $item) {
                    $item->ticket()->increment('stock', $item->quantity);
                }
            }
        });
    }

    private function isSignatureKeyValid(array $payload): bool
    {
        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $serverKey = config('midtrans.server_key');

        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        return $signatureKey === ($payload['signature_key'] ?? '');
    }
}
