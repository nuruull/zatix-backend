<?php

namespace App\Http\Controllers\API\Cashier;

use App\Models\User;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Actions\ProcessPaidOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enum\Status\OrderStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\BaseController;
use App\Enum\Type\FinancialTransactionTypeEnum;
use Illuminate\Auth\Access\AuthorizationException;

class OfflineSalesController extends BaseController
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', Rule::exists('events', 'id')->where('is_published', true)],
            'items' => 'required|array|min:1',
            'items.*.ticket_id' => 'required|exists:tickets,id,event_id,' . $request->event_id,
            'items.*.quantity' => 'required|integer|min:1',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
        ]);

        try {
            $event = Event::findOrFail($validated['event_id']);
            $this->authorize('sellOfflineTickets', $event);

            $order = DB::transaction(function () use ($validated, $event) {
                $kasir = auth()->user();

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
                    $ticket = Ticket::lockForUpdate()->find($item['ticket_id']);
                    if ($ticket->stock < $item['quantity']) {
                        throw new \Exception("Stok tiket '{$ticket->name}' tidak mencukupi.");
                    }
                    $subtotal = $ticket->price * $item['quantity'];
                    $grossAmount += $subtotal;
                    $orderItemsPayload[] = ['ticket_id' => $ticket->id, 'quantity' => $item['quantity'], 'price' => $ticket->price, 'subtotal' => $subtotal];

                    $ticket->decrement('stock', $item['quantity']);
                }

                $newOrder = Order::create([
                    'id' => Str::uuid(),
                    'user_id' => $customer->id,
                    'event_id' => $event->id,
                    'gross_amount' => $grossAmount,
                    'net_amount' => $grossAmount,
                    'status' => OrderStatusEnum::PAID,
                ]);
                $newOrder->orderItems()->createMany($orderItemsPayload);

                return $newOrder;
            });

            (new ProcessPaidOrder)->execute($order, auth()->user());

            $order->load('eTickets');

            return $this->sendResponse($order->eTickets, 'Offline sale successful. E-tickets generated.', 201);

        } catch (AuthorizationException $e) {
            // Tangkap error otorisasi secara spesifik
            return $this->sendError('Forbidden. You are not authorized to sell tickets for this event.', [], 403);
        } catch (\Exception $e) {
            Log::error('Offline Sale Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('An error occurred during the offline sale.', ['detail' => $e->getMessage()], 500);
        }
    }
}
