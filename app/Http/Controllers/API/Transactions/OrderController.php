<?php

namespace App\Http\Controllers\API\Transactions;

use Auth;

use Midtrans\Snap;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController;
use Illuminate\Validation\ValidationException;

class OrderController extends BaseController
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.ticket_id' => 'required|exists:tickets,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $orderItemsPayload = [];
        $netAmount = 0;
        $eventId = null;

        try {
            $order = DB::transaction(function () use ($validated, $user, &$orderItemsPayload, &$netAmount, &$eventId) {
                foreach ($validated['items'] as $item) {
                    $ticket = Ticket::find($item['id']);

                    if ($ticket->stock < $item['quantity']) {
                        throw ValidationException::withMessages([
                            'items' => "Stok tiket '{$ticket->name}' tidak mencukupi. Sisa stok: {$ticket->stock}."
                        ]);
                    }

                    $netAmount += $ticket->price * $item['quantity'];
                    $orderItemsPayload[] = [
                        'ticket_id' => $ticket->id,
                        'quantity' => $item['quantity'],
                        'price' => $ticket->price,
                    ];

                    // Ambil event_id dari tiket pertama
                    if (is_null($eventId)) {
                        $eventId = $ticket->event_id;
                    }
                }

                // 3. Buat record Order awal
                $order = Order::create([
                    'order_id' => Str::upper('ZTX-' . now()->format('Ymd') . '-' . Str::random(5)),
                    'user_id' => $user->id,
                    'event_id' => $eventId,
                    'net_amount' => $netAmount,
                    'status' => 'unpaid',
                ]);

                // 4. Buat record OrderItems
                $order->orderItems()->createMany($orderItemsPayload);

                // 5. Kurangi stok tiket
                foreach ($order->orderItems as $item) {
                    $item->ticket()->decrement('stock', $item->quantity);
                }

                return $order;
            });

            // 6. Hubungi Midtrans untuk mendapatkan Snap Token
            $midtransPayload = [
                'transaction_details' => [
                    'order_id' => $order->order_id,
                    'gross_amount' => $order->net_amount,
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                ],
                'item_details' => $order->orderItems->map(function ($item) {
                    return [
                        'id' => $item->ticket_id,
                        'price' => $item->price,
                        'quantity' => $item->quantity,
                        'name' => $item->ticket->name,
                    ];
                })->toArray(),
            ];

            $snapToken = Snap::getSnapToken($midtransPayload);

            // 7. Simpan Snap Token ke pesanan
            $order->update(['snap_token' => $snapToken]);

            return $this->sendResponse(
                ['snap_token' => $snapToken],
                'Payment token generated successfully.'
            );

        } catch (ValidationException $e) {
            return $this->sendError($e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Order creation failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->sendError('An unexpected error occurred.', [], 500);
        }
    }
}
