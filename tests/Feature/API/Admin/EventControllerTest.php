<?php

namespace Tests\Feature\API\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\WithFaker;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;


class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $eoOwner;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat role secara manual, HANYA yang dibutuhkan oleh kelas test ini.
        Role::create(['name' => 'super-admin', 'guard_name' => 'api']);
        Role::create(['name' => 'eo-owner', 'guard_name' => 'api']);

        // Buat pengguna
        $this->superAdmin = User::factory()->create()->assignRole('super-admin');
        $this->eoOwner = User::factory()->create()->assignRole('eo-owner');
    }

    #[Test]
    public function super_admin_can_view_published_events_list(): void
    {
        $eo = \App\Models\EventOrganizer::factory()->create(['eo_owner_id' => $this->eoOwner->id]);

        Event::factory()->count(2)->create([
            'is_published' => true,
            'eo_id' => $eo->id,
        ]);

        Event::factory()->create([
            'is_published' => false,
            'eo_id' => $eo->id,
        ]);

        Sanctum::actingAs($this->superAdmin);

        // Act
        $response = $this->getJson('/api/admin/events');

        // Assert
        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.data') // Note: data.data for paginated results
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'is_published',
                            'event_organizer'
                        ]
                    ],
                    'current_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    #[Test]
    public function non_super_admin_cannot_view_events_list(): void
    {
        // Arrange: Login sebagai EO Owner
        Sanctum::actingAs($this->eoOwner);

        // Act
        $response = $this->getJson('/api/admin/events');

        // Assert: Harus Forbidden karena middleware 'role:super-admin'
        $response->assertStatus(403);
    }
}
