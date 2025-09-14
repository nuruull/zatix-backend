<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Event;
use App\Models\Order;
use Midtrans\CoreApi;
use App\Models\Ticket;
use App\Models\Voucher;
use Illuminate\Support\Str;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\WaitingRoomService;
use App\Enum\Type\TransactionTypeEnum;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Validation\ValidationException;
use App\Events\OrderPaymentReady;


class ProcessNewOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected User $user;
    protected array $validatedData;
    protected Event $event;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, array $validatedData, Event $event)
    {
        $this->user = $user;
        $this->validatedData = $validatedData;
        $this->event = $event;
    }

    /**
     * Execute the job.
     */
    public function handle(WaitingRoomService $waitingRoom): void
    {
        try {
            $order = DB::transaction(function () {
                $validated = $this->validatedData;
                $user = $this->user;

                $orderItemsPayload = [];
                $grossAmount = 0;
                $eventId = $this->event->id;

                foreach ($validated['items'] as $item) {
                    $ticket = Ticket::lockForUpdate()->find($item['ticket_id']);

                    if ($ticket->stock < $item['quantity']) {
                        throw new \Exception("Stok tiket '{$ticket->name}' tidak mencukupi. Sisa: {$ticket->stock}.");
                    }

                    $subtotal = $ticket->price * $item['quantity'];
                    $grossAmount += $subtotal;

                    $orderItemsPayload[] = [
                        'ticket_id' => $ticket->id,
                        'quantity' => $item['quantity'],
                        'price' => $ticket->price,
                        'subtotal' => $subtotal,
                    ];
                }

                $discountAmount = 0;
                $voucherToUse = null;

                if (!empty($validated['voucher_code'])) {
                    $voucher = Voucher::where('code', $validated['voucher_code'])->lockForUpdate()->first();
                    if (!$voucher || !$voucher->is_active || $voucher->valid_until->isPast() || $voucher->usage_limit <= 0) {
                        throw new \Exception('Voucher tidak valid, kedaluwarsa, atau sudah habis.');
                    }
                    if ($voucher->discount_type === 'percentage') {
                        $discountAmount = ($grossAmount * $voucher->discount_value) / 100;
                        if ($voucher->max_amount && $discountAmount > $voucher->max_amount) {
                            $discountAmount = $voucher->max_amount;
                        }
                    } else {
                        $discountAmount = $voucher->discount_value;
                    }
                    $voucherToUse = $voucher;
                }

                $netAmount = $grossAmount - $discountAmount;
                $netAmount = max(0, $netAmount);

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

                if ($voucherToUse) {
                    $order->vouchers()->attach($voucherToUse->id, ['discount_amount_applied' => $discountAmount]);
                    $voucherToUse->decrement('usage_limit');
                }

                foreach ($order->orderItems as $item) {
                    $item->ticket()->decrement('stock', $item->quantity);
                }

                return $order;
            });

            $paymentMethod = PaymentMethod::with('bank')->findOrFail($this->validatedData['payment_method_id']);
            $chargePayload = $this->prepareMidtransPayload($order, $paymentMethod, $this->user);
            $midtransResponse = CoreApi::charge($chargePayload);

            $this->createTransactionRecords($order, $midtransResponse, $paymentMethod->id);

            broadcast(new OrderPaymentReady($order, $midtransResponse));

            Log::info("Order {$order->id} berhasil diproses dan siap dibayar oleh user {$this->user->id}.");

        } catch (\Throwable $e) {
            Log::error("Gagal memproses order untuk user {$this->user->id}: " . $e->getMessage());
            throw $e;
        } finally {
            $waitingRoom->removeUserFromActive($this->event, $this->user);
            Log::info("User {$this->user->id} dihapus dari sesi aktif untuk event {$this->event->id}.");
        }
    }

    private function prepareMidtransPayload(Order $order, PaymentMethod $paymentMethod, User $user): array
    {
        $itemDetails = $order->orderItems->map(fn($item) => [
            'id' => $item->ticket_id,
            'price' => $item->price,
            'quantity' => $item->quantity,
            'name' => Str::limit($item->ticket->name, 50),
        ])->toArray();

        if ($order->discount_amount > 0) {
            $itemDetails[] = [
                'id' => 'DISCOUNT',
                'price' => -$order->discount_amount,
                'quantity' => 1,
                'name' => 'Discount/Voucher',
            ];
        }

        $payload = [
            'payment_type' => $paymentMethod->bank->type,
            'transaction_details' => [
                'order_id' => $order->id,
                'gross_amount' => $order->net_amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
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

    private function createTransactionRecords(Order $order, $midtransResponse, $paymentMethodId): void
    {
        $transaction = $order->transactions()->create([
            'user_id' => $order->user_id,
            'grand_amount' => $order->net_amount,
            'status' => 'pending',
            'type' => TransactionTypeEnum::TRANSFER->value,
        ]);

        $transaction->paymentDetail()->create([
            'payment_method_id' => $paymentMethodId,
            'reference_id' => $midtransResponse->transaction_id,
            'va_number' => $midtransResponse->va_numbers[0]->va_number ?? $midtransResponse->permata_va_number ?? null,
            'bill_key' => $midtransResponse->bill_key ?? null,
            'biller_code' => $midtransResponse->biller_code ?? null,
            'qris_url' => $midtransResponse->actions[0]->url ?? null,
            'expiry_at' => $midtransResponse->expiry_time,
        ]);
    }

}
