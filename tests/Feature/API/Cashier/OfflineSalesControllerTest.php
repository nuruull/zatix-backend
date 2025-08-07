<?php

namespace Tests\Feature\API\Cashier;

use App\Enum\Status\OrderStatusEnum;
use App\Enum\Status\TransactionStatusEnum;
use App\Enum\Type\FinancialTransactionTypeEnum;
use App\Enum\Type\TransactionTypeEnum;
use App\Models\Bank;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodCategory;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Midtrans\CoreApi; // Import CoreApi
use Mockery; // Import Mockery
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OfflineSalesControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private User $unauthorizedUser;
    private Event $event;
    private Ticket $ticket;
    private PaymentMethod $cashPaymentMethod;
    private PaymentMethod $onlinePaymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Buat Role yang dibutuhkan
        Role::create(['name' => 'cashier', 'guard_name' => 'api']);
        Role::create(['name' => 'customer', 'guard_name' => 'api']);
        $this->cashier = User::factory()->create()->assignRole('cashier');
        $this->unauthorizedUser = User::factory()->create()->assignRole('customer');

        // 2. Buat Event Organizer dan daftarkan kasir sebagai member
        $eo = EventOrganizer::factory()->create();
        $eo->members()->attach($this->cashier->id);

        // 3. Buat Event dan Tiket dengan stok awal
        $this->event = Event::factory()->create(['eo_id' => $eo->id, 'is_published' => true]);
        $this->ticket = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'stock' => 100,
            'price' => 50000,
        ]);

        // 4. Buat Metode Pembayaran (Cash dan Online)
        $cashCategory = PaymentMethodCategory::factory()->create(['name' => 'Tunai']);
        $qrisCategory = PaymentMethodCategory::factory()->create(['name' => 'QRIS']);

        $cashProvider = Bank::factory()->create(['name' => 'Cash', 'code' => 'cash', 'type' => 'cash']);
        $qrisProvider = Bank::factory()->create(['name' => 'QRIS', 'code' => 'qris', 'type' => 'qris']);

        $this->cashPaymentMethod = PaymentMethod::factory()->create([
            'payment_method_category_id' => $cashCategory->id,
            'bank_id' => $cashProvider->id,
        ]);

        $this->onlinePaymentMethod = PaymentMethod::factory()->create([
            'payment_method_category_id' => $qrisCategory->id,
            'bank_id' => $qrisProvider->id,
        ]);
    }

    #[Test]
    public function cashier_can_successfully_create_offline_sale_with_cash(): void
    {
        // Arrange
        Sanctum::actingAs($this->cashier);
        $payload = [
            'event_id' => $this->event->id,
            'customer_name' => 'John Doe Walkin',
            'customer_email' => 'johndoe.walkin@example.com',
            'payment_method_id' => $this->cashPaymentMethod->id,
            'items' => [
                ['ticket_id' => $this->ticket->id, 'quantity' => 2]
            ]
        ];

        // Act
        $response = $this->postJson('/api/cashier/sales', $payload);

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data'); // Memastikan 2 e-tiket dibuat

        // Verifikasi semua efek samping di database
        $this->assertDatabaseHas('orders', [
            'event_id' => $this->event->id,
            'status' => OrderStatusEnum::PAID->value,
            // FIX: Removed 'payment_method_id' assertion as the column does not exist in the test schema.
        ]);
        $this->assertDatabaseHas('transactions', [
            'type' => TransactionTypeEnum::CASH->value,
            'status' => 'settlement',
            'grand_amount' => 100000, // 2 * 50000
        ]);
        $this->assertDatabaseHas('tickets', ['id' => $this->ticket->id, 'stock' => 98]); // 100 - 2
        $this->assertDatabaseHas('financial_transactions', ['type' => FinancialTransactionTypeEnum::INCOME->value, 'category' => 'Offline Ticket Sales']);
        $this->assertDatabaseCount('e_tickets', 2);
    }

    #[Test]
    public function cashier_can_successfully_create_offline_sale_with_online_payment(): void
    {
        // Arrange
        Sanctum::actingAs($this->cashier);
        $payload = [
            'event_id' => $this->event->id,
            'customer_name' => 'Jane Doe Online',
            'customer_email' => 'janedoe.online@example.com',
            'payment_method_id' => $this->onlinePaymentMethod->id,
            'items' => [
                ['ticket_id' => $this->ticket->id, 'quantity' => 1]
            ]
        ];

        // Mocking Midtrans Response
        $mockMidtransResponse = (object) [
            'transaction_id' => 'dummy-transaction-id',
            'order_id' => 'dummy-order-id',
            'transaction_status' => 'pending',
            'payment_type' => 'qris',
            'gross_amount' => '50000.00',
            'actions' => [
                (object) ['name' => 'generate-qr-code', 'method' => 'GET', 'url' => 'https://api.sandbox.midtrans.com/v2/qris/dummy-transaction-id/qr-code']
            ]
        ];

        // FIX: Use Mockery's alias/overload feature for a more reliable static method mock.
        Mockery::mock('alias:' . CoreApi::class)
            ->shouldReceive('charge')
            ->once()
            ->andReturn($mockMidtransResponse);

        // Act
        $response = $this->postJson('/api/cashier/sales', $payload);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.transaction_id', 'dummy-transaction-id');

        // Verifikasi efek samping di database untuk alur online
        $this->assertDatabaseHas('orders', [
            'event_id' => $this->event->id,
            'status' => OrderStatusEnum::UNPAID->value, // Harus PENDING
        ]);
        $this->assertDatabaseHas('transactions', [
            'type' => TransactionTypeEnum::TRANSFER->value,
            'status' => TransactionStatusEnum::PENDING->value,
            'grand_amount' => 50000,
        ]);
        $this->assertDatabaseHas('tickets', ['id' => $this->ticket->id, 'stock' => 99]); // Stok tetap berkurang
        $this->assertDatabaseMissing('financial_transactions', ['category' => 'Offline Ticket Sales']); // Income belum tercatat
        $this->assertDatabaseCount('e_tickets', 0); // E-ticket belum dibuat
    }

    #[Test]
    public function sale_fails_if_ticket_stock_is_insufficient(): void
    {
        // Arrange
        Sanctum::actingAs($this->cashier);
        $payload = [
            'event_id' => $this->event->id,
            'customer_name' => 'Unlucky Customer',
            'customer_email' => 'unlucky@example.com', // FIX: Added missing customer_email key.
            'payment_method_id' => $this->cashPaymentMethod->id,
            'items' => [
                ['ticket_id' => $this->ticket->id, 'quantity' => 101] // Melebihi stok (100)
            ]
        ];

        // Act
        $response = $this->postJson('/api/cashier/sales', $payload);

        // Assert
        $response->assertStatus(422) // Unprocessable Entity
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation Failed');

        // Pastikan tidak ada perubahan di database
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('tickets', ['id' => $this->ticket->id, 'stock' => 100]); // Stok tidak berubah
    }

    #[Test]
    public function unauthorized_user_cannot_create_offline_sale(): void
    {
        // Arrange: Login sebagai pengguna biasa (customer)
        Sanctum::actingAs($this->unauthorizedUser);
        $payload = [
            'event_id' => $this->event->id,
            'payment_method_id' => $this->cashPaymentMethod->id,
            'items' => [['ticket_id' => $this->ticket->id, 'quantity' => 1]]
        ];

        // Act
        $response = $this->postJson('/api/cashier/sales', $payload);

        // Assert: Harus Forbidden
        $response->assertStatus(403);
    }
}
