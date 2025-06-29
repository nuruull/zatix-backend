<?php

namespace App\Http\Controllers\API\Transactions;

use Auth;

use Midtrans\Snap;
use App\Models\User;
use App\Models\Order;
use Midtrans\CoreApi;
use App\Models\Ticket;
use App\Models\Voucher;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController;
use Illuminate\Validation\ValidationException;

class OrderController extends BaseController
{
    public function store(Request $request)
    {
        // 1. Validasi Input Awal
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.ticket_id' => 'required|exists:tickets,id',
            'items.*.quantity' => 'required|integer|min:1|max:10', // Batasi maks pembelian per jenis tiket
            'payment_method_id' => 'required|exists:payment_methods,id',
            'voucher_code' => 'nullable|string|exists:vouchers,code',
        ]);

        $user = Auth::user();
        $paymentMethod = PaymentMethod::with('bank')->findOrFail($validated['payment_method_id']);

        try {
            $orderData = []; // Variabel untuk menampung hasil kalkulasi

            // Memulai transaksi database untuk memastikan integritas data
            $order = DB::transaction(function () use ($validated, $user, &$orderData) {

                // --- Langkah 2: Kalkulasi Harga dan Stok ---
                $orderItemsPayload = [];
                $grossAmount = 0;
                $eventId = null;

                foreach ($validated['items'] as $item) {
                    $ticket = Ticket::lockForUpdate()->find($item['ticket_id']); // Lock baris untuk mencegah race condition

                    if ($ticket->stock < $item['quantity']) {
                        throw ValidationException::withMessages([
                            'items' => "Stok tiket '{$ticket->name}' tidak mencukupi. Sisa: {$ticket->stock}."
                        ]);
                    }

                    $subtotal = $ticket->price * $item['quantity'];
                    $grossAmount += $subtotal;

                    $orderItemsPayload[] = [
                        'ticket_id' => $ticket->id,
                        'quantity' => $item['quantity'],
                        'price' => $ticket->price,
                        'subtotal' => $subtotal,
                        // 'discount' bisa ditambahkan di sini jika ada diskon per item
                    ];
                    if (is_null($eventId))
                        $eventId = $ticket->event_id;
                }

                // --- Langkah 3: Kalkulasi Voucher (jika ada) ---
                $discountAmount = 0;
                $voucherId = null;
                if (!empty($validated['voucher_code'])) {
                    $voucher = Voucher::where('code', $validated['voucher_code'])->first();
                    // Tambahkan validasi voucher (aktif, valid_until, usage_limit)
                    if ($voucher) {
                        if ($voucher->discount_type === 'percentage') {
                            $discountAmount = ($grossAmount * $voucher->discount_value) / 100;
                            if ($voucher->max_discount_amount && $discountAmount > $voucher->max_discount_amount) {
                                $discountAmount = $voucher->max_discount_amount;
                            }
                        } else {
                            $discountAmount = $voucher->discount_value;
                        }
                        $voucherId = $voucher->id;
                    }
                }

                $netAmount = $grossAmount - $discountAmount;
                if ($netAmount < 0)
                    $netAmount = 0; // Pastikan total tidak minus

                // --- Langkah 4: Buat Record di Database ---
                $order = Order::create([
                    'id' => Str::uuid(),
                    'user_id' => $user->id,
                    'event_id' => $eventId,
                    'gross_amount' => $grossAmount,
                    'discount_amount' => $discountAmount,
                    'net_amount' => $netAmount,
                    'status' => 'unpaid',
                ]);

                $order->orderItems()->createMany($orderItemsPayload);

                if ($voucherId) {
                    $order->vouchers()->attach($voucherId, ['discount_amount_applied' => $discountAmount]);
                }

                // --- Langkah 5: Kurangi Stok Tiket ---
                foreach ($order->orderItems as $item) {
                    $item->ticket()->decrement('stock', $item->quantity);
                }

                // Simpan data kalkulasi untuk digunakan di luar transaksi
                $orderData = compact('netAmount', 'orderItemsPayload');

                return $order;
            });

            // --- Langkah 6: Siapkan & Panggil Midtrans Core API ---
            // Ini dilakukan SETELAH transaksi DB berhasil
            $chargePayload = $this->prepareMidtransPayload($order, $paymentMethod, $user);
            $midtransResponse = CoreApi::charge($chargePayload);

            // --- Langkah 7: Buat Record Transaksi Pembayaran ---
            $this->createTransactionRecords($order, $midtransResponse, $paymentMethod->id);

            // --- Langkah 8: Kirim Respons ke Frontend ---
            return $this->sendResponse(
                $midtransResponse,
                'Payment details retrieved successfully. Please complete the payment.'
            );

        } catch (ValidationException $e) {
            return $this->sendError('Validation Failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Order creation failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            // Tambahkan job untuk mengembalikan stok jika gagal di sini
            return $this->sendError('An unexpected error occurred during order creation.', [], 500);
        }
    }

    /**
     * untuk menyiapkan payload yang akan dikirim ke Midtrans.
     */
    private function prepareMidtransPayload(Order $order, PaymentMethod $paymentMethod, User $user): array
    {
        $payload = [
            'payment_type' => $paymentMethod->bank->type,
            'transaction_details' => [
                'order_id' => $order->id, // Kirim UUID sebagai order_id
                'gross_amount' => $order->net_amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'item_details' => $order->orderItems->map(fn($item) => [
                'id' => $item->ticket_id,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'name' => Str::limit($item->ticket->name, 50),
            ])->toArray(),
        ];

        if ($payload['payment_type'] === 'echannel') {
            $payload['echannel'] = ['bill_info1' => 'Payment For:', 'bill_info2' => 'Ticket ' . Str::limit($order->event->name, 20)];
        } else if ($payload['payment_type'] === 'bank_transfer') {
            $payload['bank_transfer'] = ['bank' => $paymentMethod->bank->code];
        }

        return $payload;
    }

    /**
     * untuk menyimpan hasil dari Midtrans ke database.
     */
    private function createTransactionRecords(Order $order, $midtransResponse, $paymentMethodId): void
    {
        // Buat record transaksi utama
        $transaction = $order->transactions()->create([
            'user_id' => $order->user_id,
            'grand_amount' => $order->net_amount,
            'status' => 'pending' // Status dari Midtrans
        ]);

        // Buat record detail pembayaran
        $transaction->paymentDetail()->create([
            'payment_method_id' => $paymentMethodId,
            'reference_id' => $midtransResponse->transaction_id,
            'va_number' => $midtransResponse->va_numbers[0]->va_number ?? $midtransResponse->permata_va_number ?? null,
            'bill_key' => $midtransResponse->bill_key ?? null,
            'biller_code' => $midtransResponse->biller_code ?? null,
            'qris_url' => $midtransResponse->actions[0]->url ?? null, // Untuk QRIS & GoPay
            'expiry_at' => $midtransResponse->expiry_time,
        ]);
    }
}
