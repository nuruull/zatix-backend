<?php

namespace Tests\Feature\API\Cashier;

use App\Enum\Status\OrderStatusEnum;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

    protected function setUp(): void
    {
        parent::setUp();

        // Buat role dan user yang dibutuhkan
        Role::create(['name' => 'cashier', 'guard_name' => 'api']);
        Role::create(['name' => 'customer', 'guard_name' => 'api']);
        $this->cashier = User::factory()->create()->assignRole('cashier');
        $this->unauthorizedUser = User::factory()->create()->assignRole('customer');

        // Buat event dan tiket dengan stok awal
        $eo = EventOrganizer::factory()->create();
        $eo->members()->attach($this->cashier->id); // Daftarkan kasir ke EO
        $this->event = Event::factory()->create(['eo_id' => $eo->id, 'is_published' => true]);
        $this->ticket = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'stock' => 100,
        ]);
    }

    #[Test]
    public function cashier_can_successfully_create_offline_sale(): void
    {
        // Arrange
        Sanctum::actingAs($this->cashier);
        $payload = [
            'event_id' => $this->event->id,
            'customer_name' => 'John Doe Walkin',
            'customer_email' => 'johndoe.walkin@example.com',
            'items' => [
                ['ticket_id' => $this->ticket->id, 'quantity' => 2]
            ]
        ];

        // Act
        $response = $this->postJson('/api/cashier/sales', $payload);

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        // Verifikasi semua efek samping di database
        $this->assertDatabaseHas('orders', [
            'event_id' => $this->event->id,
            'status' => OrderStatusEnum::PAID->value,
        ]);
        $this->assertDatabaseHas('tickets', ['id' => $this->ticket->id, 'stock' => 98]); // 100 - 2
        $this->assertDatabaseHas('financial_transactions', ['type' => 'income', 'category' => 'Offline Ticket Sales']);
        $this->assertDatabaseCount('e_tickets', 2);
    }

    #[Test]
    public function unauthorized_user_cannot_create_offline_sale(): void
    {
        // Arrange: Login sebagai pengguna biasa
        Sanctum::actingAs($this->unauthorizedUser);
        $payload = [
            'event_id' => $this->event->id,
            'items' => [['ticket_id' => $this->ticket->id, 'quantity' => 1]]
        ];

        // Act
        $response = $this->postJson('/api/cashier/sales', $payload);

        // Assert: Harus Forbidden karena middleware 'role:cashier'
        $response->assertStatus(403);
    }
}
