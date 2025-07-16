<?php

namespace Tests\Feature\API\Transactions;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use Laravel\Sanctum\Sanctum;
use App\Models\EventOrganizer;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use App\Models\FinancialTransaction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class FinancialTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $eoOwner;
    private User $financeUser;
    private User $otherFinanceUser;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat roles
        Role::create(['name' => 'eo-owner', 'guard_name' => 'api']);
        Role::create(['name' => 'finance', 'guard_name' => 'api']);

        // Buat users
        $this->eoOwner = User::factory()->create()->assignRole('eo-owner');
        $this->financeUser = User::factory()->create()->assignRole('finance');
        $this->otherFinanceUser = User::factory()->create()->assignRole('finance');

        // Buat EO dan Event
        $eo = EventOrganizer::factory()->create(['eo_owner_id' => $this->eoOwner->id]);
        $this->event = Event::factory()->create(['eo_id' => $eo->id]);

        // Daftarkan finance user sebagai anggota EO
        $eo->members()->attach($this->financeUser->id);
    }

    // --- TESTING CREATE (STORE) ---

    #[Test]
    public function finance_user_can_create_transaction_with_proof_file(): void
    {
        Storage::fake('public'); // Gunakan fake storage
        Sanctum::actingAs($this->financeUser);

        $file = UploadedFile::fake()->image('proof.jpg');
        $data = [
            'type' => 'expense',
            'description' => 'Sewa sound system',
            'amount' => 5000000,
            'transaction_date' => '2025-07-20',
            'proof_file' => $file,
        ];

        $response = $this->postJson("/api/events/{$this->event->id}/financial-transactions", $data);

        $response->assertStatus(201)->assertJsonPath('data.description', 'Sewa sound system');

        $transaction = FinancialTransaction::first();
        $this->assertNotNull($transaction->proof_trans_url);
        Storage::disk('public')->assertExists($transaction->proof_trans_url);
    }

    #[Test]
    public function eo_owner_cannot_create_transaction(): void
    {
        Sanctum::actingAs($this->eoOwner); // Login sebagai EO Owner
        $data = ['type' => 'income', 'description' => 'Sponsorship', 'amount' => 10000000, 'transaction_date' => '2025-07-21'];

        $response = $this->postJson("/api/events/{$this->event->id}/financial-transactions", $data);

        $response->assertStatus(403); // Harusnya Forbidden
    }

    #[Test]
    public function store_fails_with_invalid_data(): void
    {
        Sanctum::actingAs($this->financeUser);
        $data = ['type' => 'invalid_type', 'description' => 'Test', 'amount' => 'abc', 'transaction_date' => '20-07-2025'];

        $response = $this->postJson("/api/events/{$this->event->id}/financial-transactions", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'amount', 'transaction_date']);
    }

    // --- TESTING READ (INDEX) ---

    #[Test]
    public function authorized_users_can_view_transaction_list(): void
    {
        // Arrange: Buat 2 transaksi dan pastikan 'recorded_by_user_id' diisi
        FinancialTransaction::factory()->count(2)->create([
            'event_id' => $this->event->id,
            'recorded_by_user_id' => $this->financeUser->id, // <-- PENTING!
        ]);

        // Act & Assert untuk Finance
        Sanctum::actingAs($this->financeUser);
        $this->getJson("/api/events/{$this->event->id}/financial-transactions")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data'); // <-- Path yang sudah diperbaiki

        // Act & Assert untuk EO Owner
        Sanctum::actingAs($this->eoOwner);
        $this->getJson("/api/events/{$this->event->id}/financial-transactions")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data'); // <-- Path yang sudah diperbaiki
    }

    // --- TESTING UPDATE & DELETE ---

    #[Test]
    public function user_who_recorded_it_can_update_transaction(): void
    {
        $transaction = FinancialTransaction::factory()->create([
            'event_id' => $this->event->id,
            'recorded_by_user_id' => $this->financeUser->id
        ]);

        Sanctum::actingAs($this->financeUser);
        $response = $this->putJson("/api/financial-transactions/{$transaction->id}", ['amount' => 12345]);

        $response->assertStatus(200)->assertJsonPath('data.amount', 12345);
    }

    #[Test]
    public function another_finance_user_cannot_update_or_delete_transaction(): void
    {
        $transaction = FinancialTransaction::factory()->create([
            'event_id' => $this->event->id,
            'recorded_by_user_id' => $this->financeUser->id // Dibuat oleh financeUser
        ]);

        Sanctum::actingAs($this->otherFinanceUser); // Login sebagai finance user lain

        // Coba update
        $this->putJson("/api/financial-transactions/{$transaction->id}", ['amount' => 999])
            ->assertStatus(403);

        // Coba delete
        $this->deleteJson("/api/financial-transactions/{$transaction->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function eo_owner_can_delete_transaction_and_its_file(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('proof_to_delete.jpg');
        $path = $file->store('proofs/transactions', 'public');

        $transaction = FinancialTransaction::factory()->create([
            'event_id' => $this->event->id,
            'recorded_by_user_id' => $this->financeUser->id,
            'proof_trans_url' => $path
        ]);

        // Pastikan file ada sebelum dihapus
        Storage::disk('public')->assertExists($path);

        Sanctum::actingAs($this->eoOwner); // Owner bisa menghapus
        $response = $this->deleteJson("/api/financial-transactions/{$transaction->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('financial_transactions', ['id' => $transaction->id]);
        Storage::disk('public')->assertMissing($path); // Pastikan file juga terhapus
    }
}
