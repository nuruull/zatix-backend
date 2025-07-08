<?php

namespace Tests\Feature\API\Transactions;

use Tests\TestCase;
use App\Models\Bank;
use App\Models\User;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Voucher;
use Midtrans\CoreApi;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $event;
    private $ticket;
    private $paymentMethod;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Siapkan data dasar yang akan sering digunakan
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create(['is_published' => true]);
        $this->ticket = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'name' => 'Tiket Regular',
            'price' => 100000,
            'stock' => 50,
        ]);
        $bank = Bank::factory()->create(['type' => 'bank_transfer', 'code' => 'bca']);
        $this->paymentMethod = PaymentMethod::factory()->create(['bank_id' => $bank->id]);

        // 2. Konfigurasi Midtrans untuk testing (opsional, tapi praktik yang baik)
        Config::set('midtrans.server_key', 'dummy-server-key');
        Config::set('midtrans.client_key', 'dummy-client-key');
        Config::set('midtrans.is_production', false);
    }

    /**
     * Pengguna berhasil membuat pesanan tanpa voucher.
     */
    #[Test]
    public function user_can_successfully_create_an_order_without_voucher()
    {
        // --- ARRANGE ---
        // Siapkan payload request dari client
        $payload = [
            'items' => [
                ['ticket_id' => $this->ticket->id, 'quantity' => 2],
            ],
            'payment_method_id' => $this->paymentMethod->id,
        ];

        // --- MOCKING ---
        // Kita "memalsukan" Midtrans\CoreApi.
        // Kita tidak ingin benar-benar mengirim request ke Midtrans saat testing.
        $this->mock(CoreApi::class, function ($mock) {
            $mock->shouldReceive('charge')
                ->once() // Harapannya method charge akan dipanggil 1x
                ->andReturn((object) [ // dan akan mengembalikan object response palsu ini
                    'transaction_id' => 'dummy-transaction-id',
                    'order_id' => 'some-uuid-from-db', // akan diisi oleh order->id
                    'transaction_status' => 'pending',
                    'payment_type' => 'bank_transfer',
                    'va_numbers' => [
                        ['bank' => 'bca', 'va_number' => '1234567890']
                    ],
                    'expiry_time' => now()->addDay()->toDateTimeString(),
                ]);
        });

        // --- ACT ---
        // Jalankan request ke endpoint
        $response = $this->actingAs($this->user)->postJson('/api/v1/orders', $payload);

        // --- ASSERT ---
        // Verifikasi respons
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment details retrieved successfully. Please complete the payment.'
            ]);

        // Verifikasi perubahan di database
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'gross_amount' => 200000, // 2 x 100000
            'discount_amount' => 0,
            'net_amount' => 200000,
            'status' => 'unpaid',
        ]);

        $this->assertDatabaseHas('order_items', [
            'ticket_id' => $this->ticket->id,
            'quantity' => 2,
            'price' => 100000
        ]);

        // Pastikan stok tiket berkurang
        $this->assertDatabaseHas('tickets', [
            'id' => $this->ticket->id,
            'stock' => 48, // 50 - 2
        ]);

        // Pastikan record transaksi dibuat
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'grand_amount' => 200000,
            'status' => 'pending'
        ]);
    }

    /**
     * Pengguna berhasil membuat pesanan dengan voucher yang valid.
     */
    #[Test]
    public function user_can_successfully_create_an_order_with_a_valid_voucher()
    {
        // --- ARRANGE ---
        $voucher = Voucher::factory()->create([
            'code' => 'HEMAT20',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'usage_limit' => 10,
            'valid_until' => now()->addWeek(),
        ]);

        $payload = [
            'items' => [
                ['ticket_id' => $this->ticket->id, 'quantity' => 2],
            ],
            'payment_method_id' => $this->paymentMethod->id,
            'voucher_code' => 'HEMAT20',
        ];

        // --- MOCKING ---
        $this->mock(CoreApi::class, function ($mock) {
            $mock->shouldReceive('charge')->once()->andReturn((object) ['transaction_id' => 'dummy-id', 'va_numbers' => [['va_number' => '123']], 'expiry_time' => now()]);
        });

        // --- ACT ---
        $response = $this->actingAs($this->user)->postJson('/api/v1/orders', $payload);

        // --- ASSERT ---
        $response->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'gross_amount' => 200000,
            'discount_amount' => 20000,
            'net_amount' => 180000, // 200000 - 20000
        ]);

        // Pastikan kuota voucher berkurang
        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'usage_limit' => 9, // 10 - 1
        ]);

        // Pastikan relasi order-voucher tercatat
        $order = Order::first();
        $this->assertDatabaseHas('order_voucher', [
            'order_id' => $order->id,
            'voucher_id' => $voucher->id,
        ]);
    }

    /**
     * Pesanan gagal jika stok tiket tidak mencukupi.
     */
    #[Test]
    public function order_fails_if_ticket_stock_is_insufficient()
    {
        // --- ARRANGE ---
        $this->ticket->update(['stock' => 1]); // Set stok hanya 1

        $payload = [
            'items' => [
                ['ticket_id' => $this->ticket->id, 'quantity' => 2], // Coba beli 2
            ],
            'payment_method_id' => $this->paymentMethod->id,
        ];

        // --- ACT ---
        $response = $this->actingAs($this->user)->postJson('/api/v1/orders', $payload);

        // --- ASSERT ---
        $response->assertStatus(422) // Harusnya error validasi
            ->assertJsonPath('errors.items.0', "Stok tiket 'Tiket Regular' tidak mencukupi. Sisa: 1.");

        // Pastikan tidak ada order yang dibuat
        $this->assertDatabaseCount('orders', 0);

        // Pastikan stok tidak berubah
        $this->assertDatabaseHas('tickets', ['id' => $this->ticket->id, 'stock' => 1]);
    }

    /**
     * Pesanan gagal jika menggunakan voucher yang tidak valid.
     */
    #[Test]
    public function order_fails_if_voucher_is_invalid()
    {
        // --- ARRANGE ---
        Voucher::factory()->create([
            'code' => 'KADALUARSA',
            'valid_until' => now()->subDay(), // Sudah expired
        ]);

        $payload = [
            'items' => [
                ['ticket_id' => $this->ticket->id, 'quantity' => 1],
            ],
            'payment_method_id' => $this->paymentMethod->id,
            'voucher_code' => 'KADALUARSA',
        ];

        // --- ACT ---
        $response = $this->actingAs($this->user)->postJson('/api/v1/orders', $payload);

        // --- ASSERT ---
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['voucher_code']);

        $this->assertDatabaseCount('orders', 0);
    }
}
