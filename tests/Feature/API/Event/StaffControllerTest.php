<?php

namespace Tests\Feature\Api\Event;

use Tests\TestCase;
use App\Models\User;
use App\Models\EventOrganizer;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Notifications\WelcomeAndSetPasswordNotification;
use PHPUnit\Framework\Attributes\Test;

class StaffControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $eoOwner;
    protected User $anotherEoOwner;
    protected EventOrganizer $eventOrganizer;
    protected EventOrganizer $anotherEventOrganizer;
    protected array $staffRoles = ['finance', 'crew', 'cashier'];

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Buat semua role yang dibutuhkan dengan guard 'api'
        Role::create(['name' => 'eo-owner', 'guard_name' => 'api']);
        foreach ($this->staffRoles as $roleName) {
            Role::create(['name' => $roleName, 'guard_name' => 'api']);
        }

        // 2. Buat EO Owner utama beserta profil EO-nya untuk testing
        $this->eoOwner = User::factory()->create()->assignRole('eo-owner');
        $this->eventOrganizer = EventOrganizer::factory()->create(['eo_owner_id' => $this->eoOwner->id]);

        // 3. Buat EO Owner kedua untuk menguji otorisasi
        $this->anotherEoOwner = User::factory()->create()->assignRole('eo-owner');
        $this->anotherEventOrganizer = EventOrganizer::factory()->create(['eo_owner_id' => $this->anotherEoOwner->id]);
    }

    //======================================================================
    // STORE METHOD TESTS (US21, US22, US23)
    //======================================================================

    #[Test]
    public function an_eo_owner_can_create_a_new_staff_member(): void
    {
        // Arrange
        Notification::fake(); // Mencegat notifikasi agar tidak benar-benar terkirim
        $this->actingAs($this->eoOwner, 'sanctum');

        $staffData = [
            'name' => 'Budi Finance',
            'email' => 'budi.finance@example.com',
            'role' => 'finance'
        ];

        // Act
        $response = $this->postJson(route('staff.store'), $staffData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'budi.finance@example.com')
            ->assertJsonPath('data.role', 'finance');

        $this->assertDatabaseHas('users', ['email' => 'budi.finance@example.com']);
        $newStaff = User::whereEmail('budi.finance@example.com')->first();
        $this->assertTrue($newStaff->hasRole('finance', 'api'));
        $this->assertTrue($this->eventOrganizer->members->contains($newStaff));

        // Assert notifikasi untuk set password terkirim ke staff baru
        Notification::assertSentTo($newStaff, WelcomeAndSetPasswordNotification::class);
    }

    #[Test]
    public function store_fails_if_role_is_not_a_valid_staff_role(): void
    {
        $this->actingAs($this->eoOwner, 'sanctum');
        $staffData = ['name' => 'Test', 'email' => 'test@test.com', 'role' => 'super-admin']; // Role tidak valid
        $response = $this->postJson(route('staff.store'), $staffData);
        $response->assertStatus(422)->assertJsonValidationErrors('role');
    }

    #[Test]
    public function store_fails_if_email_already_exists(): void
    {
        $this->actingAs($this->eoOwner, 'sanctum');
        $staffData = ['name' => 'Test', 'email' => $this->anotherEoOwner->email, 'role' => 'crew']; // Email sudah ada
        $response = $this->postJson(route('staff.store'), $staffData);
        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    //======================================================================
    // INDEX METHOD TESTS
    //======================================================================

    #[Test]
    public function an_eo_owner_can_list_only_their_own_staff(): void
    {
        // Arrange: Buat 2 staff untuk EO pertama, dan 1 staff untuk EO kedua
        $staff1 = User::factory()->create();
        $staff2 = User::factory()->create();
        $this->eventOrganizer->members()->attach([$staff1->id, $staff2->id]);

        $anotherStaff = User::factory()->create();
        $this->anotherEventOrganizer->members()->attach($anotherStaff->id);

        $this->actingAs($this->eoOwner, 'sanctum');

        // Act
        $response = $this->getJson(route('staff.index'));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.data'); // Pastikan hanya 2 staff yang tampil
    }

    //======================================================================
    // UPDATE METHOD TESTS
    //======================================================================

    #[Test]
    public function an_eo_owner_can_update_their_staff_role(): void
    {
        // Arrange
        $staff = User::factory()->create()->assignRole('crew');
        $this->eventOrganizer->members()->attach($staff->id);
        $this->actingAs($this->eoOwner, 'sanctum');

        // Act
        $response = $this->putJson(route('staff.update', $staff), [
            'role' => 'finance'
        ]);

        // Assert
        $response->assertStatus(200);
        $this->assertTrue($staff->fresh()->hasRole('finance', 'api'));
        $this->assertFalse($staff->fresh()->hasRole('crew', 'api'));
    }

    #[Test]
    public function an_eo_owner_cannot_update_staff_from_another_eo(): void
    {
        // Arrange
        $anotherStaff = User::factory()->create();
        $this->anotherEventOrganizer->members()->attach($anotherStaff->id);
        $this->actingAs($this->eoOwner, 'sanctum');

        $response = $this->putJson(route('staff.update', $anotherStaff), ['name' => 'Hacked']);

        $response->assertStatus(403);
    }

    //======================================================================
    // DESTROY METHOD TESTS
    //======================================================================

    #[Test]
    public function an_eo_owner_can_remove_a_staff_member_from_their_team(): void
    {
        // Arrange
        $staff = User::factory()->create();

        $cashierRole = Role::where('name', 'cashier')->where('guard_name', 'api')->first();
        $staff->assignRole($cashierRole);

        $this->eventOrganizer->members()->attach($staff->id);
        $this->actingAs($this->eoOwner, 'sanctum');

        $this->assertDatabaseHas('model_has_roles', ['model_id' => $staff->id]);
        $this->assertTrue($this->eventOrganizer->members->contains($staff));

        // Act
        $response = $this->deleteJson(route('staff.destroy', $staff));

        // Assert
        $response->assertStatus(200);
        $this->assertFalse($this->eventOrganizer->fresh()->members->contains($staff));
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $staff->id]);
    }
}
