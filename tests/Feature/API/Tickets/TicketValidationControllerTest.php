<?php

namespace Tests\Feature\API\Tickets;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Models\Order;
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

    /**
     * Menyiapkan lingkungan untuk setiap test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Buat role yang diperlukan dengan guard 'api'
        Role::create(['name' => 'crew', 'guard_name' => 'api']);
        Role::create(['name' => 'eo-owner', 'guard_name' => 'api']);

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

    // /**
    //  * Endpoint index berhasil mengambil riwayat check-in.
    //  */
    // #[Test]
    // public function index_retrieves_checked_in_history_for_an_event()
    // {
    //     // Pastikan database transaction selesai
    //     DB::beginTransaction();

    //     try {
    //         // Arrange: Buat 3 tiket yang sudah check-in untuk event ini
    //         $order = Order::factory()->create(['event_id' => $this->event->id, 'status' => OrderStatusEnum::PAID->value]);
    //         $order->refresh();

    //         $eTickets = ETicket::factory()->count(3)->create([
    //             'order_id' => $order->id,
    //             'ticket_id' => $order->orderItems->first()->ticket_id,
    //             'checked_in_at' => now(),
    //             'checked_in_by' => $this->crew->id,
    //         ]);

    //         $order2 = Order::factory()->create(['event_id' => $this->event->id, 'status' => OrderStatusEnum::PAID->value]);
    //         $order2->refresh();

    //         ETicket::factory()->create([
    //             'order_id' => $order2->id,
    //             'ticket_id' => $order2->orderItems->first()->ticket_id,
    //         ]);

    //         DB::commit();

    //         // Pastikan data sudah tersimpan
    //         $this->assertDatabaseCount('e_tickets', 4);
    //         $this->assertDatabaseCount('e_tickets', 3, ['checked_in_at' => ['!=', null]]);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         throw $e;
    //     }

    //     $token = $this->crew->createToken('test-token')->plainTextToken;

    //     // Act
    //     $response = $this->withHeaders([
    //         'Authorization' => 'Bearer ' . $token,
    //     ])->getJson('/api/e-tickets?event_id=' . $this->event->id);

    //     // Assert
    //     $response->assertStatus(200)
    //         ->assertJsonCount(3, 'data.data');
    // }
}
