<?php

namespace Tests\Feature\API\Tickets;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\ETicket;
use Laravel\Sanctum\Sanctum;
use App\Models\EventOrganizer;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Enum\Status\OrderStatusEnum;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TicketValidationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $crew;
    private User $otherCrew;
    private Event $event;
    private EventOrganizer $eo;
    private User $customer;
    private Ticket $ticket;

    /**
     * Menyiapkan lingkungan untuk setiap test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Buat role yang diperlukan
        Role::create(['name' => 'crew', 'guard_name' => 'api']);
        Role::create(['name' => 'eo-owner', 'guard_name' => 'api']);
        Role::create(['name' => 'customer', 'guard_name' => 'api']); // <-- Tambahkan role customer

        // Buat EO utama dan crew-nya
        $this->eo = EventOrganizer::factory()->create();
        $this->crew = User::factory()->create()->assignRole('crew');
        $this->eo->members()->attach($this->crew->id);

        // Buat event utama yang dimiliki oleh EO utama
        $this->event = Event::factory()->create(['eo_id' => $this->eo->id, 'is_published' => true]);

        // Buat EO dan crew lain untuk skenario negatif
        $otherEo = EventOrganizer::factory()->create();
        $this->otherCrew = User::factory()->create()->assignRole('crew');
        $otherEo->members()->attach($this->otherCrew->id);

        // --- TAMBAHAN UNTUK MEMPERBAIKI ERROR ---
        // 1. Buat satu user customer yang akan kita gunakan di semua test
        $this->customer = User::factory()->create()->assignRole('customer');

        // 2. Buat satu jenis tiket default untuk event utama kita
        $this->ticket = Ticket::factory()->create(['event_id' => $this->event->id]);
    }

    /**
     * Crew berhasil memvalidasi tiket yang valid.
     */
    #[Test]
    public function crew_can_successfully_validate_a_valid_ticket()
    {
        // Arrange
        $order = Order::factory()->create(['event_id' => $this->event->id, 'status' => OrderStatusEnum::PAID->value]);
        $order->refresh(); // Muat ulang model untuk mendapatkan relasi dari factory hook
        $eTicket = ETicket::factory()->create(['order_id' => $order->id, 'ticket_id' => $order->orderItems->first()->ticket_id]);

        $token = $this->crew->createToken('test-token')->plainTextToken;

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/e-tickets/validate', [
                    'ticket_code' => $eTicket->ticket_code,
                ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Check-in was successful!',
            ]);

        $this->assertDatabaseHas('e_tickets', [
            'id' => $eTicket->id,
            'checked_in_by' => $this->crew->id,
        ]);

        $this->assertDatabaseMissing('e_tickets', [
            'id' => $eTicket->id,
            'checked_in_at' => null,
        ]);
    }

    /**
     * Validasi gagal jika tiket sudah pernah digunakan.
     */
    #[Test]
    public function validation_fails_if_ticket_is_already_checked_in()
    {
        // Arrange
        // Langkah 1: Buat order yang valid
        $order = Order::factory()->create([
            'event_id' => $this->event->id,
            'status' => OrderStatusEnum::PAID->value
        ]);
        $order->refresh();

        // Langkah 2: Buat eTicket yang valid
        $eTicket = ETicket::factory()->create([
            'order_id' => $order->id,
            'ticket_id' => $order->orderItems->first()->ticket_id,
        ]);

        $eTicket->update([
            'checked_in_at' => now(), // pastikan ini nama field yang benar
            'checked_in_by' => $this->crew->id,
        ]);

        // Refresh untuk memastikan perubahan tersimpan
        $eTicket->refresh();

        // Debug: Pastikan tiket sudah dalam status checked-in
        $this->assertNotNull($eTicket->checked_in_at, 'Ticket should be marked as checked in');

        // Buat token untuk autentikasi
        $token = $this->crew->createToken('test-token')->plainTextToken;

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/e-tickets/validate', [
                    'ticket_code' => $eTicket->ticket_code,
                ]);

        // Assert
        $response->assertStatus(409)
            ->assertJsonPath('errors.status', 'ALREADY_CHECKED_IN')
            ->assertJsonPath('errors.data.checked_in_by', $this->crew->name);
    }

    /**
     * Validasi gagal jika crew bukan anggota dari EO pemilik event.
     */
    #[Test]
    public function validation_fails_if_crew_is_not_from_the_correct_eo()
    {
        $order = Order::factory()->create(['event_id' => $this->event->id, 'status' => OrderStatusEnum::PAID->value]);
        $order->refresh();
        $eTicket = ETicket::factory()->create(['order_id' => $order->id, 'ticket_id' => $order->orderItems->first()->ticket_id]);
        $token = $this->otherCrew->createToken('test-token')->plainTextToken; // Gunakan token dari crew lain

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/e-tickets/validate', [
                    'ticket_code' => $eTicket->ticket_code,
                ]);

        // Assert
        $response->assertStatus(403)
            ->assertJsonPath('errors.status', 'UNAUTHORIZED_CREW');
    }

    /**
     * Validasi gagal jika order tiket belum lunas.
     */
    #[Test]
    public function validation_fails_if_order_is_not_paid()
    {
        // Arrange: Buat e-ticket dari order yang statusnya 'cancelled'
        $order = Order::factory()->create(['event_id' => $this->event->id, 'status' => OrderStatusEnum::CANCELLED->value]);
        $order->refresh(); // PERBAIKAN: Muat ulang model untuk mendapatkan relasi dari factory hook
        $eTicket = ETicket::factory()->create(['order_id' => $order->id, 'ticket_id' => $order->orderItems->first()->ticket_id]);
        $token = $this->crew->createToken('test-token')->plainTextToken;

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/e-tickets/validate', [
                    'ticket_code' => $eTicket->ticket_code,
                ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonPath('errors.status', 'ORDER_NOT_PAID');
    }

    /**
     * Validasi gagal jika tiket tidak ditemukan.
     */
    #[Test]
    public function validation_fails_if_ticket_code_does_not_exist()
    {
        $token = $this->crew->createToken('test-token')->plainTextToken;

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/e-tickets/validate', [
                    'ticket_code' => 'KODE-PALSU-12345',
                ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ticket_code']);
    }

    #[Test]
    public function index_returns_paginated_and_formatted_history_via_resource(): void
    {
        // Arrange: Siapkan data yang kompleks untuk memastikan semua filter bekerja
        // 1. Buat 3 tiket yang sudah check-in untuk event ini (harus muncul)
        // Kita variasikan waktunya agar bisa menguji pengurutan
        $order1 = Order::factory()->create(['event_id' => $this->event->id, 'user_id' => $this->customer->id]);
        $ticket1 = ETicket::factory()->create(['order_id' => $order1->id, 'user_id' => $this->customer->id, 'ticket_id' => $this->ticket->id, 'checked_in_at' => now()->subMinutes(20), 'checked_in_by' => $this->crew->id]);
        $ticket2 = ETicket::factory()->create(['order_id' => $order1->id, 'user_id' => $this->customer->id, 'ticket_id' => $this->ticket->id, 'checked_in_at' => now()->subMinutes(10), 'checked_in_by' => $this->crew->id]);
        $latestTicket = ETicket::factory()->create(['order_id' => $order1->id, 'user_id' => $this->customer->id, 'ticket_id' => $this->ticket->id, 'checked_in_at' => now()->subMinute(), 'checked_in_by' => $this->crew->id]);

        // 2. Tiket yang BELUM check-in untuk event ini (TIDAK boleh muncul)
        $order2 = Order::factory()->create(['event_id' => $this->event->id, 'user_id' => $this->customer->id]);
        ETicket::factory()->create(['order_id' => $order2->id, 'user_id' => $this->customer->id, 'ticket_id' => $this->ticket->id, 'checked_in_at' => null]);

        // 3. Tiket yang sudah check-in tapi untuk event LAIN (TIDAK boleh muncul)
        $otherEvent = Event::factory()->create();
        $otherTicket = Ticket::factory()->create(['event_id' => $otherEvent->id]);
        $order3 = Order::factory()->create(['event_id' => $otherEvent->id, 'user_id' => $this->customer->id]);
        ETicket::factory()->create(['order_id' => $order3->id, 'user_id' => $this->customer->id, 'ticket_id' => $otherTicket->id, 'checked_in_at' => now()]);

        // Act
        Sanctum::actingAs($this->crew);
        $response = $this->getJson('/api/e-tickets?event_id=' . $this->event->id);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.ticket_code', $latestTicket->ticket_code)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'ticket_code',
                        'attendee_name',
                        'ticket_type',
                        'checked_in_at',
                        'scanned_by'
                    ]
                ],
                'links',
                'meta',
                'success',
                'message'
            ]);
    }
}
