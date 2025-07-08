<?php

namespace Tests\Feature\API\Transactions;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Enum\Status\OrderStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class MidtransWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private $order;
    private $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('midtrans.server_key', 'dummy-server-key');

        // 1. Buat user dan event.
        $user = User::factory()->create();
        $event = Event::factory()->create();

        // 2. Buat TIKET dengan STOK YANG PASTI (misalnya 10).
        $this->ticket = Ticket::factory()->create([
            'event_id' => $event->id,
            'stock' => 10, // <-- KUNCI: Stok awal yang pasti.
        ]);

        // 3. Buat ORDER dasar.
        $this->order = Order::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => OrderStatusEnum::UNPAID,
            'net_amount' => 50000,
        ]);

        // 4. Hapus item acak yang mungkin dibuat oleh OrderFactory.
        $this->order->orderItems()->delete();

        // 5. Buat SATU item order dengan kuantitas TEPAT 2.
        $this->order->orderItems()->create([
            'ticket_id' => $this->ticket->id,
            'quantity' => 2, // Beli 2 tiket. Stok sekarang sisa 8.
            'price' => 25000,
            'subtotal' => 50000,
        ]);

        // 6. Kurangi stok secara manual di database untuk mensimulasikan pembelian.
        $this->ticket->decrement('stock', 2);

        $this->order->refresh();
    }

    /**
     * Webhook berhasil menangani pembayaran sukses (settlement).
     */
    #[Test]
    public function webhook_handles_successful_payment_and_generates_etickets()
    {
        // --- ARRANGE ---
        // Siapkan payload notifikasi palsu dari Midtrans
        $payload = [
            'transaction_status' => 'settlement',
            'order_id' => $this->order->id,
            'status_code' => '200',
            'gross_amount' => $this->order->net_amount . '.00',
            'fraud_status' => 'accept',
            // Signature key HARUS di-generate dengan benar agar validasi lolos
            'signature_key' => hash('sha512', $this->order->id . '200' . $this->order->net_amount . '.00' . config('midtrans.server_key')),
        ];

        // --- ACT ---
        $response = $this->postJson('/api/webhooks/midtrans', $payload);

        // --- ASSERT ---
        $response->assertStatus(200)
            ->assertJson(['message' => 'Notification processed successfully.']);

        // Cek status order berubah menjadi PAID
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatusEnum::PAID->value,
        ]);

        // Cek e-ticket sudah di-generate sesuai quantity
        $this->assertDatabaseCount('e_tickets', 2);
        $this->assertDatabaseHas('e_tickets', [
            'order_id' => $this->order->id,
            'user_id' => $this->order->user_id,
            'ticket_id' => $this->ticket->id,
        ]);
    }

    /**
     * Webhook berhasil menangani pembayaran yang expired dan mengembalikan stok.
     */
    #[Test]
    public function webhook_handles_expired_payment_and_restores_stock()
    {
        // --- ARRANGE ---
        $payload = [
            'transaction_status' => 'expire',
            'order_id' => $this->order->id,
            'status_code' => '201',
            'gross_amount' => $this->order->net_amount . '.00',
            'fraud_status' => 'accept',
            'signature_key' => hash('sha512', $this->order->id . '201' . $this->order->net_amount . '.00' . config('midtrans.server_key')),
        ];

        // --- ACT ---
        $response = $this->postJson('/api/webhooks/midtrans', $payload);

        // --- ASSERT ---
        $response->assertStatus(200);

        // Cek status order berubah menjadi EXPIRED
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatusEnum::EXPIRED->value,
        ]);

        // Cek stok tiket dikembalikan
        // Stok awal 10, dibeli 2 -> sisa 8. Expired, harusnya kembali jadi 10.
        $this->assertDatabaseHas('tickets', [
            'id' => $this->ticket->id,
            'stock' => 10,
        ]);

        // Pastikan tidak ada e-ticket yang dibuat
        $this->assertDatabaseCount('e_tickets', 0);
    }

    /**
     * Webhook menolak notifikasi dengan signature key yang tidak valid.
     */
    #[Test]
    public function webhook_rejects_notification_with_invalid_signature()
    {
        // --- ARRANGE ---
        $payload = [
            'transaction_status' => 'settlement',
            'order_id' => $this->order->id,
            'status_code' => '200',
            'gross_amount' => $this->order->net_amount . '.00',
            'signature_key' => 'ini-adalah-signature-yang-salah', // Signature salah
        ];

        // --- ACT ---
        $response = $this->postJson('/api/webhooks/midtrans', $payload);

        // --- ASSERT ---
        $response->assertStatus(403) // Forbidden
            ->assertJson(['message' => 'Invalid signature']);

        // Pastikan status order tidak berubah
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatusEnum::UNPAID->value,
        ]);
    }
}
