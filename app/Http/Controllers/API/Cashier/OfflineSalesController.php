<?php

namespace App\Http\Controllers\API\Cashier;

// Tambahkan use statement untuk Midtrans\CoreApi
use Midtrans\CoreApi;
use App\Models\User;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use Illuminate\Validation\Rule;
use App\Actions\ProcessPaidOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enum\Status\OrderStatusEnum;
use App\Enum\Type\TransactionTypeEnum;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\BaseController;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class OfflineSalesController extends BaseController
{
    public function store(Request $request)
    {
        // 1. Validasi input, sekarang termasuk payment_method_id
        $validated = $request->validate([
            'event_id' => ['required', Rule::exists('events', 'id')->where('is_published', true)],
            'items' => 'required|array|min:1',
            'items.*.ticket_id' => 'required|exists:tickets,id,event_id,' . $request->event_id,
            'items.*.quantity' => 'required|integer|min:1',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'payment_method_id' => 'required|exists:payment_methods,id,is_active,1',
        ]);

        try {
            DB::beginTransaction();

            // 2. Ambil detail event, metode pembayaran, dan kasir
            $event = Event::findOrFail($validated['event_id']);
            $this->authorize('sellOfflineTickets', $event);

            $paymentMethod = PaymentMethod::with('bank')->findOrFail($validated['payment_method_id']);
            $kasir = auth()->user();

            // --- Logika Bersama: Buat customer dan hitung total ---
            $customer = User::firstOrCreate(
                ['email' => $validated['customer_email']],
                ['name' => $validated['customer_name'], 'password' => Hash::make(Str::random(10))]
            );
            if (!$customer->hasRole('customer')) {
                $customer->assignRole('customer');
            }

            $grossAmount = 0;
            $orderItemsPayload = [];

            foreach ($validated['items'] as $item) {
                $ticket = Ticket::find($item['ticket_id']);
                if ($ticket->stock < $item['quantity']) {
                    throw ValidationException::withMessages(['items' => "Stok tiket '{$ticket->name}' tidak mencukupi."]);
                }
                $subtotal = $ticket->price * $item['quantity'];
                $grossAmount += $subtotal;
                $orderItemsPayload[] = ['ticket_id' => $ticket->id, 'quantity' => $item['quantity'], 'price' => $ticket->price, 'subtotal' => $subtotal];
            }

            // 3. PERCABANGAN LOGIKA BERDASARKAN METODE PEMBAYARAN
            // =======================================================

            // --- ALUR TUNAI (CASH) ---
            if ($paymentMethod->bank->code === 'cash') {

                $order = Order::create([
                    'id' => Str::uuid(),
                    'user_id' => $customer->id,
                    'event_id' => $event->id,
                    'gross_amount' => $grossAmount,
                    'net_amount' => $grossAmount,
                    'status' => OrderStatusEnum::PAID, // Langsung PAID
                    'payment_method_id' => $paymentMethod->id,
                ]);
                $order->orderItems()->createMany($orderItemsPayload);

                $order->transactions()->create([
                    'user_id' => $customer->id,
                    'grand_amount' => $order->net_amount,
                    'status' => 'settlement', // Status langsung lunas/selesai
                    'type' => TransactionTypeEnum::CASH, // Gunakan tipe CASH dari Enum
                ]);

                // Kurangi stok tiket
                foreach ($order->orderItems as $item) {
                    $item->ticket()->decrement('stock', $item->quantity);
                }


                // Jalankan proses untuk order yang sudah lunas (buat e-tiket, catat income)
                (new ProcessPaidOrder)->execute($order, $kasir);

                DB::commit();

                $order->load('eTickets');
                return $this->sendResponse($order->eTickets, 'Offline sale (cash) successful. E-tickets generated.', 201);
            }
            // --- ALUR ONLINE (MIDTRANS) ---
            else {
                $order = Order::create([
                    'id' => Str::uuid(),
                    'user_id' => $customer->id,
                    'event_id' => $event->id,
                    'gross_amount' => $grossAmount,
                    'net_amount' => $grossAmount,
                    'status' => OrderStatusEnum::UNPAID, // Status PENDING
                    'payment_method_id' => $paymentMethod->id,
                ]);
                $order->orderItems()->createMany($orderItemsPayload);

                // Kurangi stok tiket
                foreach ($order->orderItems as $item) {
                    $item->ticket()->decrement('stock', $item->quantity);
                }

                // Panggil Midtrans (mengadaptasi dari OrderController)
                $chargePayload = $this->prepareMidtransPayload($order, $paymentMethod, $customer);
                $midtransResponse = CoreApi::charge($chargePayload);

                // Simpan detail transaksi dari Midtrans (opsional, tapi sangat direkomendasikan)
                $order->transactions()->create([
                    'user_id' => $customer->id,
                    'grand_amount' => $order->net_amount,
                    'reference_id' => $midtransResponse->transaction_id,
                    'status' => $midtransResponse->transaction_status,
                    'type' => TransactionTypeEnum::TRANSFER,
                    // ... kolom lain jika ada
                ]);

                DB::commit();

                return $this->sendResponse((array) $midtransResponse, 'Transaction created. Please complete the payment.');
            }

        } catch (AuthorizationException $e) {
            DB::rollBack();
            return $this->sendError('Forbidden. You are not authorized to sell tickets for this event.', [], 403);
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->sendError('Validation Failed', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Offline Sale Failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->sendError('An error occurred during the offline sale.', ['detail' => $e->getMessage()], 500);
        }
    }

    /**
     * Helper method untuk menyiapkan payload Midtrans.
     * Diadaptasi dari OrderController Anda.
     */
    private function prepareMidtransPayload(Order $order, PaymentMethod $paymentMethod, User $customer): array
    {
        $itemDetails = $order->orderItems->map(fn($item) => [
            'id' => $item->ticket_id,
            'price' => (int) $item->price,
            'quantity' => $item->quantity,
            'name' => Str::limit($item->ticket->name, 50),
        ])->toArray();

        $payload = [
            'payment_type' => $paymentMethod->bank->type,
            'transaction_details' => [
                'order_id' => $order->id,
                'gross_amount' => (int) $order->net_amount,
            ],
            'customer_details' => [
                'first_name' => $customer->name,
                'email' => $customer->email,
            ],
            'item_details' => $itemDetails,
        ];

        if ($payload['payment_type'] === 'echannel') {
            $payload['echannel'] = ['bill_info1' => 'Payment For:', 'bill_info2' => 'Ticket ' . Str::limit($order->event->name, 20)];
        } else if ($payload['payment_type'] === 'bank_transfer') {
            $payload['bank_transfer'] = ['bank' => $paymentMethod->bank->code];
        }

        return $payload;
    }
}
