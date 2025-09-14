<?php

namespace App\Http\Controllers\API\Transactions;

use Auth;

use Throwable;
use Midtrans\Snap;
use App\Models\User;
use App\Models\Event;
use App\Models\Order;
use Midtrans\CoreApi;
use App\Models\Ticket;
use App\Models\Voucher;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Jobs\ProcessNewOrder;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\WaitingRoomService;
use Illuminate\Support\Facades\Cache;
use App\Enum\Type\TransactionTypeEnum;
use App\Http\Controllers\BaseController;
use Illuminate\Validation\ValidationException;

class OrderController extends BaseController
{
    protected WaitingRoomService $waitingRoom;

    // public function __construct(WaitingRoomService $waitingRoom)
    // {
    //     $this->waitingRoom = $waitingRoom;
    // }

    public function store(Request $request)
    {
        try {
            // 1. Validasi Input Awal
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.ticket_id' => 'required|exists:tickets,id',
                'items.*.quantity' => 'required|integer|min:1|max:10',
                'payment_method_id' => 'required|exists:payment_methods,id',
                'voucher_code' => 'nullable|string|exists:vouchers,code',
            ]);

            $user = Auth::user();

            $firstTicket = Ticket::find($validated['items'][0]['ticket_id']);
            if (!$firstTicket) {
                return $this->sendError('Invalid ticket provided.', [], 404);
            }
            $event = $firstTicket->event;

            $response = Cache::lock('process-order-event-' . $event->id, 10)->get(function () use ($event, $user, $validated) {
                $waitingRoom = app(WaitingRoomService::class);

                // A. Cek jika user sudah diizinkan masuk sebelumnya
                if ($waitingRoom->isAllowedToProceed($event, $user)) {
                    ProcessNewOrder::dispatch($user, $validated, $event);
                    return $this->sendResponse([], 'Your order is being processed. You will be notified shortly.', 202);
                }

                // B. Cek jika masih ada kapasitas di ruang checkout
                if ($waitingRoom->hasCapacity($event)) {
                    $waitingRoom->allowUserToProceed($event, $user);
                    ProcessNewOrder::dispatch($user, $validated, $event);
                    return $this->sendResponse([], 'You have entered the checkout. Your order is being processed.', 202);
                }

                // C. Jika penuh, masukkan user ke dalam antrian
                $queueData = $waitingRoom->addUserToQueue($event, $user);

                return $this->sendResponse(
                    $queueData, // Berisi 'position' dan 'estimated_wait_time_minutes'
                    'The event is currently busy. You have been placed in a waiting room.',
                    429 // HTTP Status: Too Many Requests
                );
            });

            return $response;
        } catch (ValidationException $e) {
            return $this->sendError('Validation Failed', $e->errors(), 422);
        } catch (Throwable $e) {
            Log::error('Order initiation failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->sendError('An unexpected error occurred before processing your order.', [], 500);
        }
    }

    public function show(Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            return $this->sendError('Not Found', 'The requested order was not found.', 404);
        }

        $order->load(['orderItems.ticket', 'event', 'transactions.paymentDetail']);

        return $this->sendResponse($order, 'Order details retrieved successfully.');
    }
}
