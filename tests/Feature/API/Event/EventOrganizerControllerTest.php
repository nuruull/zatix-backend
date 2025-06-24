<?php

namespace Tests\Feature\API\Event;

use Tests\TestCase;
use App\Models\User;
use App\Models\EventOrganizer;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EventOrganizerControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $superAdminUser;
    protected User $eoOwnerUser;
    protected User $anotherEoOwnerUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Buat semua role yang dibutuhkan dengan guard 'api'
        Role::create(['name' => 'super-admin', 'guard_name' => 'api']);
        Role::create(['name' => 'eo-owner', 'guard_name' => 'api']);

        // 2. Buat user untuk setiap role
        $this->superAdminUser = User::factory()->create()->assignRole('super-admin');
        $this->eoOwnerUser = User::factory()->create()->assignRole('eo-owner');
        $this->anotherEoOwnerUser = User::factory()->create()->assignRole('eo-owner');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/public/event-organizers'));
        parent::tearDown();
    }

    #[Test]
    public function eo_owner_can_create_their_profile(): void
    {
        $this->actingAs($this->eoOwnerUser, 'sanctum');

        $data = [
            'name' => 'My Awesome EO',
            'organizer_type' => 'individual',
            'phone_no_eo' => '081234567890',
            'address_eo' => 'Jl. Pahlawan No. 123',
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ];

        File::makeDirectory(storage_path('app/public/event-organizers/logo'), 0755, true, true);

        $response = $this->postJson(route('event-organizers.store'), $data);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('event_organizers', ['name' => 'My Awesome EO']);

        $logoPath = $response->json('data.logo');

        // --- PERUBAHAN: Gunakan File::exists untuk cek file fisik ---
        $this->assertTrue(File::exists(storage_path('app/public/' . $logoPath)));
    }

    #[Test]
    public function an_eo_owner_cannot_create_a_second_profile(): void
    {
        // Arrange: Buat profil pertama untuk user ini
        EventOrganizer::factory()->create(['eo_owner_id' => $this->eoOwnerUser->id]);
        $this->actingAs($this->eoOwnerUser, 'sanctum');

        // --- PENYEMPURNAAN: Kirim data yang valid agar tidak gagal di validasi ---
        // Ini memastikan kita benar-benar menguji logika if ($user->eventOrganizer()->exists())
        $data = [
            'name' => 'Second Profile',
            'organizer_type' => 'individual',
            'phone_no_eo' => '0811111111',
            'address_eo' => 'Alamat kedua',
        ];

        // Act: Coba buat profil kedua
        $response = $this->postJson(route('event-organizers.store'), $data);

        // Assert
        $response->assertStatus(409); // 409 Conflict (sudah benar)
        $this->assertDatabaseCount('event_organizers', 1);
    }

    //======================================================================
    // UPDATE METHOD TESTS
    //======================================================================

    #[Test]
    public function an_eo_owner_can_update_their_own_profile(): void
    {
        // Arrange
        $organizer = EventOrganizer::factory()->create(['eo_owner_id' => $this->eoOwnerUser->id]);
        $this->actingAs($this->eoOwnerUser, 'sanctum');

        $updateData = ['name' => 'Updated Awesome EO Name'];

        // Act
        // --- PERBAIKAN: Gunakan ->id karena controller menerima $id, bukan objek ---
        $response = $this->putJson(route('event-organizers.update', $organizer->id), $updateData);

        // Assert
        $response->assertStatus(200);
        $this->assertDatabaseHas('event_organizers', [
            'id' => $organizer->id,
            'name' => 'Updated Awesome EO Name'
        ]);
    }

    #[Test]
    public function an_eo_owner_can_update_their_logo(): void
    {
        // Arrange
        $organizer = EventOrganizer::factory()->create([
            'eo_owner_id' => $this->eoOwnerUser->id,
            'logo' => null // Awalnya tidak punya logo
        ]);
        $this->actingAs($this->eoOwnerUser, 'sanctum');

        $newLogo = UploadedFile::fake()->image('new_logo.png');
        $updateData = ['logo' => $newLogo];

        // Buat direktori tujuan secara manual
        File::makeDirectory(storage_path('app/public/event-organizers/logo'), 0755, true, true);

        // Act
        // --- PERBAIKAN: Gunakan postJson dengan _method PUT untuk upload file ---
        $response = $this->postJson(route('event-organizers.update', $organizer->id), array_merge($updateData, ['_method' => 'PUT']));

        // Assert
        $response->assertStatus(200);

        $organizer->refresh(); // Ambil data terbaru dari DB
        $this->assertNotNull($organizer->logo);
        $this->assertTrue(File::exists(storage_path('app/public/' . $organizer->logo)));
    }

    #[Test]
    public function an_eo_owner_cannot_update_another_owners_profile(): void
    {
        // Arrange: Buat profil yang dimiliki oleh user lain
        $organizerOfAnotherUser = EventOrganizer::factory()->create(['eo_owner_id' => $this->anotherEoOwnerUser->id]);

        // Login sebagai user pertama
        $this->actingAs($this->eoOwnerUser, 'sanctum');

        // Act: Coba update profil milik user lain
        // --- PERBAIKAN: Gunakan ->id ---
        $response = $this->putJson(route('event-organizers.update', $organizerOfAnotherUser->id), ['name' => 'Hacked Name']);

        // Assert
        $response->assertStatus(403); // Forbidden (sudah benar)
    }
}
