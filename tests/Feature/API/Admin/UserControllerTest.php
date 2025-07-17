<?php

namespace Tests\Feature\API\Admin;

use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;


class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $eoOwner;

    /**
     * Menyiapkan data dasar yang dibutuhkan oleh banyak test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Buat role-role yang dibutuhkan
        Role::create(['name' => 'super-admin', 'guard_name' => 'api']);
        Role::create(['name' => 'eo-owner', 'guard_name' => 'api']);
        Role::create(['name' => 'finance', 'guard_name' => 'api']);

        // Buat pengguna dengan peran masing-masing
        $this->superAdmin = User::factory()->create()->assignRole('super-admin');
        $this->eoOwner = User::factory()->create()->assignRole('eo-owner');
    }

    // =================================================================
    // TESTING INDEX (GET /users)
    // =================================================================

    #[Test]
    public function super_admin_can_view_user_list(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->getJson('/api/admin/users');

        // Assert
        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            // Periksa jumlah item di dalam kunci 'data' di level atas
            ->assertJsonCount(2, 'data') // <-- Path sudah diperbaiki
            // Periksa struktur keseluruhan
            ->assertJsonStructure([
                'data' => [ // Array dari user
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'roles'
                    ]
                ],
                'links', // Kunci paginasi
                'meta',  // Kunci paginasi
                'success', // Kunci dari 'additional'
                'message'
            ]);
    }

    #[Test]
    public function non_super_admin_cannot_view_user_list(): void
    {
        Sanctum::actingAs($this->eoOwner); // Login sebagai EO Owner

        $this->getJson('/api/admin/users')
            ->assertStatus(403); // Harusnya Forbidden
    }

    // =================================================================
    // TESTING GET ROLES (GET /roles)
    // =================================================================

    #[Test]
    public function super_admin_can_get_all_available_roles(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/admin/roles')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data') // Kita membuat 3 role di setUp
            ->assertJsonFragment(['name' => 'super-admin'])
            ->assertJsonFragment(['name' => 'eo-owner'])
            ->assertJsonFragment(['name' => 'finance']);
    }

    // =================================================================
    // TESTING UPDATE ROLES (PUT /users/{user}/roles)
    // =================================================================

    #[Test]
    public function super_admin_can_update_user_roles(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Awalnya $eoOwner hanya punya peran 'eo-owner'
        $this->assertTrue($this->eoOwner->hasRole('eo-owner'));
        $this->assertFalse($this->eoOwner->hasRole('finance'));

        $payload = ['roles' => ['eo-owner', 'finance']];

        // Act: Update perannya
        $response = $this->putJson("/api/admin/users/{$this->eoOwner->id}/roles", $payload);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.roles'); // Pastikan sekarang punya 2 peran

        // Periksa ulang di database
        $this->eoOwner->refresh();
        $this->assertTrue($this->eoOwner->hasRole('eo-owner'));
        $this->assertTrue($this->eoOwner->hasRole('finance'));
    }

    #[Test]
    public function update_roles_fails_if_role_does_not_exist(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // 'investor' adalah peran yang tidak ada di database
        $payload = ['roles' => ['investor']];

        $response = $this->putJson("/api/admin/users/{$this->eoOwner->id}/roles", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['roles.0']);
    }
}
