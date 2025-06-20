<?php

namespace Tests\Feature\API\Event;

use Tests\TestCase;
use App\Models\User;
use App\Models\EventOrganizer;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EventOrganizerControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * User yang akan diautentikasi untuk setiap test.
     * @var \App\Models\User
     */
    protected User $superAdminUser;
    protected User $eoOwnerUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Buat semua role yang dibutuhkan dengan guard 'api'
        Role::create(['name' => 'super-admin', 'guard_name' => 'api']);
        Role::create(['name' => 'eo-owner', 'guard_name' => 'api']);

        // 2. Buat user untuk setiap role
        $this->superAdminUser = User::factory()->create();
        $this->superAdminUser->assignRole('super-admin');

        $this->eoOwnerUser = User::factory()->create();
        $this->eoOwnerUser->assignRole('eo-owner');
    }
    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/public/event-organizers'));
        parent::tearDown();
    }

    //======================================================================
    // INDEX METHOD TESTS
    //======================================================================

    #[Test]
    public function super_admin_can_get_a_list_of_event_organizers(): void
    {
        // Arrange
        EventOrganizer::factory()->count(3)->create();
        $this->actingAs($this->superAdminUser, 'sanctum'); // Login sebagai super-admin

        // Act
        $response = $this->getJson(route('event-organizer.index'));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    //======================================================================
    // SHOW METHOD TESTS
    //======================================================================

    #[Test]
    public function super_admin_can_get_a_single_event_organizer(): void
    {
        // Arrange
        $organizer = EventOrganizer::factory()->create();
        $this->actingAs($this->superAdminUser, 'sanctum'); // Login sebagai super-admin

        // Act
        $response = $this->getJson(route('event-organizer.show', $organizer->id));

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.id', $organizer->id);
    }

    //======================================================================
    // STORE METHOD TESTS
    //======================================================================

    #[Test]
    public function eo_owner_can_create_an_event_organizer(): void
    {
        // Arrange
        $this->actingAs($this->eoOwnerUser, 'sanctum'); // Login sebagai eo-owner
        $file = UploadedFile::fake()->image('logo.jpg');
        $data = [
            'name' => 'My Awesome EO',
            'logo' => $file,
        ];

        // Buat direktori tujuan secara manual agar ->move() di Trait berhasil
        File::makeDirectory(storage_path('app/public/event-organizers/logo'), 0755, true, true);

        // Act
        $response = $this->postJson(route('event-organizer.store'), $data);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('event_organizers', ['name' => 'My Awesome EO']);

        // Cek file sungguhan di disk
        $logoPath = $response->json('data.logo');
        $this->assertTrue(File::exists(storage_path('app/public/' . $logoPath)));
    }

    #[Test]
    public function store_fails_for_user_without_eo_owner_role(): void
    {
        // Arrange
        $userWithoutRole = User::factory()->create(); // User tanpa role
        $this->actingAs($userWithoutRole, 'sanctum');

        // Act
        $response = $this->postJson(route('event-organizer.store'), ['name' => 'Test']);

        // Assert
        $response->assertStatus(403); // Harusnya Forbidden karena tidak punya role
    }

    //======================================================================
    // UPDATE METHOD TESTS
    //======================================================================

    #[Test]
    public function eo_owner_can_update_an_event_organizer(): void
    {
        // Arrange
        $this->actingAs($this->eoOwnerUser, 'sanctum'); // Login sebagai eo-owner

        // Buat kondisi awal dengan file sungguhan
        $folderPath = storage_path('app/public/event-organizers/logo');
        File::makeDirectory($folderPath, 0755, true, true);

        $oldLogoFile = UploadedFile::fake()->image('old_logo.jpg');
        $oldLogoName = time() . '_old.' . $oldLogoFile->getClientOriginalExtension();
        $oldLogoFile->move($folderPath, $oldLogoName);
        $oldLogoDbPath = 'event-organizers/logo/' . $oldLogoName;

        // Buat organizer yang dimiliki oleh eoOwnerUser dan punya logo lama
        $organizer = EventOrganizer::factory()->create([
            'logo' => $oldLogoDbPath,
            'eo_owner_id' => $this->eoOwnerUser->id
        ]);

        $newLogo = UploadedFile::fake()->image('new_logo.png');
        $updateData = ['name' => 'Updated EO Name', 'logo' => $newLogo];

        // Act
        // Gunakan putJson karena route Anda menggunakan Route::put()
        $response = $this->postJson(route('event-organizer.update', $organizer->id), array_merge($updateData, ['_method' => 'PUT']));

        // Assert
        $response->assertStatus(200);
        $this->assertDatabaseHas('event_organizers', ['name' => 'Updated EO Name']);

        $newLogoDbPath = EventOrganizer::find($organizer->id)->logo;
        $this->assertTrue(File::exists(storage_path('app/public/' . $newLogoDbPath)));
        $this->assertFalse(File::exists(storage_path('app/public/' . $oldLogoDbPath)));
    }
}
