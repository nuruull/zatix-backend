<?php

namespace Tests\Feature\API\Event;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Models\Rundown;
use Laravel\Sanctum\Sanctum;
use App\Models\EventOrganizer;
use Spatie\Permission\Models\Role;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RundownControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $eoOwner;
    private User $crew;
    private User $unauthorizedUser;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat role-role yang dibutuhkan
        Role::create(['name' => 'eo-owner', 'guard_name' => 'api']);
        Role::create(['name' => 'crew', 'guard_name' => 'api']);
        Role::create(['name' => 'customer', 'guard_name' => 'api']);

        // Buat pengguna dengan peran masing-masing
        $this->eoOwner = User::factory()->create()->assignRole('eo-owner');
        $this->crew = User::factory()->create()->assignRole('crew');
        $this->unauthorizedUser = User::factory()->create()->assignRole('customer');

        // Buat EO dan Event yang dimiliki oleh eoOwner
        $eo = EventOrganizer::factory()->create(['eo_owner_id' => $this->eoOwner->id]);
        $this->event = Event::factory()->create(['eo_id' => $eo->id]);

        // Daftarkan crew sebagai anggota EO
        $eo->members()->attach($this->crew->id);
    }

    // =================================================================
    // TESTING CREATE (STORE)
    // =================================================================

    #[Test]
    public function eo_owner_can_create_rundown_for_their_event(): void
    {
        // Arrange
        Sanctum::actingAs($this->eoOwner);

        // Buat array PHP biasa, bukan dari model instance.
        // Ini memberi kita kontrol penuh atas format string.
        $rundownData = [
            'title' => 'Test Rundown Title',
            'description' => 'This is a test description.',
            'start_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'order' => 1,
        ];

        // Act
        $response = $this->postJson("/api/events/{$this->event->id}/rundowns", $rundownData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('rundowns', [
            'event_id' => $this->event->id,
            'title' => $rundownData['title']
        ]);
    }

    #[Test]
    public function crew_cannot_create_rundown(): void
    {
        Sanctum::actingAs($this->crew);
        $rundownData = Rundown::factory()->make()->toArray();

        $response = $this->postJson("/api/events/{$this->event->id}/rundowns", $rundownData);

        $response->assertStatus(403); // Harusnya Forbidden
    }

    // =================================================================
    // TESTING READ (INDEX & SHOW)
    // =================================================================

    #[Test]
    public function authorized_users_can_view_rundown_list(): void
    {
        // Arrange: Buat 2 rundown untuk event ini
        Rundown::factory()->count(2)->create(['event_id' => $this->event->id]);
        // Buat 1 rundown untuk event lain (tidak boleh muncul)
        Rundown::factory()->create(['event_id' => Event::factory()->create()->id]);

        // Act & Assert untuk EO Owner
        Sanctum::actingAs($this->eoOwner);
        $this->getJson("/api/events/{$this->event->id}/rundowns")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Act & Assert untuk Crew
        Sanctum::actingAs($this->crew);
        $this->getJson("/api/events/{$this->event->id}/rundowns")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function unauthorized_user_cannot_view_rundown_list(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $this->getJson("/api/events/{$this->event->id}/rundowns")
            ->assertStatus(403);
    }

    #[Test]
    public function eo_owner_can_view_a_specific_rundown(): void
    {
        $rundown = Rundown::factory()->create(['event_id' => $this->event->id]);

        Sanctum::actingAs($this->eoOwner);
        $this->getJson("/api/rundowns/{$rundown->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $rundown->id);
    }

    // =================================================================
    // TESTING UPDATE
    // =================================================================

    #[Test]
    public function eo_owner_can_update_their_rundown(): void
    {
        $rundown = Rundown::factory()->create(['event_id' => $this->event->id]);
        $updateData = ['title' => 'Updated Title'];

        Sanctum::actingAs($this->eoOwner);
        $this->putJson("/api/rundowns/{$rundown->id}", $updateData)
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('rundowns', ['id' => $rundown->id, 'title' => 'Updated Title']);
    }

    #[Test]
    public function crew_cannot_update_rundown(): void
    {
        $rundown = Rundown::factory()->create(['event_id' => $this->event->id]);
        $updateData = ['title' => 'Updated by Crew'];

        Sanctum::actingAs($this->crew);
        $this->putJson("/api/rundowns/{$rundown->id}", $updateData)
            ->assertStatus(403);
    }

    // =================================================================
    // TESTING DELETE
    // =================================================================

    #[Test]
    public function eo_owner_can_delete_their_rundown(): void
    {
        $rundown = Rundown::factory()->create(['event_id' => $this->event->id]);

        Sanctum::actingAs($this->eoOwner);
        $this->deleteJson("/api/rundowns/{$rundown->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('rundowns', ['id' => $rundown->id]);
    }
}
