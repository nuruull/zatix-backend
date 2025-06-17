<?php

namespace Tests\Feature\API\Event;

use Tests\TestCase;
use App\Models\User;
use App\Models\EventOrganizer;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\File;

class EventOrganizerControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * User yang akan diautentikasi untuk setiap test.
     * @var \App\Models\User
     */
    protected $user;

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/public/event-organizers'));
        parent::tearDown();
    }

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Membuat user dan mengautentikasinya untuk semua request dalam test ini
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum'); // atau 'api' sesuai guard Anda
    }

    //======================================================================
    // INDEX METHOD TESTS
    //======================================================================

    #[Test]
    public function it_can_get_a_list_of_event_organizers()
    {
        // Arrange: Buat beberapa data dummy
        EventOrganizer::factory()->count(3)->create(['eo_owner_id' => $this->user->id]);

        // Act: Panggil endpoint index
        $response = $this->getJson(route('event-organizer.index'));

        // Assert: Pastikan response sukses dan strukturnya benar
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'List of Event Organizers',
            ])
            ->assertJsonCount(3, 'data');
    }

    //======================================================================
    // SHOW METHOD TESTS
    //======================================================================

    #[Test]
    public function it_can_get_a_single_event_organizer()
    {
        // Arrange: Buat satu data dummy
        $organizer = EventOrganizer::factory()->create();

        // Act: Panggil endpoint show
        $response = $this->getJson(route('event-organizer.show', $organizer->id));

        // Assert: Pastikan response sukses dan data yang diterima sesuai
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Event Organizer found',
                'data' => [
                    'id' => $organizer->id,
                    'name' => $organizer->name,
                ]
            ]);
    }

    #[Test]
    public function it_returns_404_when_showing_a_non_existent_event_organizer()
    {
        // Act: Panggil endpoint show dengan ID yang tidak ada
        $response = $this->getJson(route('event-organizer.show', 999));

        // Assert: Pastikan response adalah Not Found (404)
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Event Organizer not found',
            ]);
    }

    //======================================================================
    // STORE METHOD TESTS
    //======================================================================

    #[Test]
    public function it_can_create_an_event_organizer_with_a_logo(): void
    {
        $file = UploadedFile::fake()->image('logo.jpg');
        $data = [
            'name' => 'My Awesome EO',
            'logo' => $file,
        ];

        $response = $this->postJson(route('event-organizer.store'), $data);

        $response->assertStatus(201);

        $logoPath = $response->json('data.logo');
        $this->assertNotNull($logoPath);

        // Gunakan assertTrue dengan File::exists() untuk memeriksa file sungguhan
        $this->assertTrue(
            File::exists(storage_path('app/public/' . $logoPath)),
            "File tidak ditemukan di disk pada path: " . $logoPath
        );
    }

    #[Test]
    public function it_can_create_an_event_organizer_without_a_logo()
    {
        // Arrange: Siapkan data tanpa logo
        $data = [
            'name' => 'EO Tanpa Logo',
            'description' => 'Deskripsi singkat.',
        ];

        // Act
        $response = $this->postJson(route('event-organizer.store'), $data);

        // Assert
        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'EO Tanpa Logo']);

        $this->assertDatabaseHas('event_organizers', [
            'name' => 'EO Tanpa Logo',
            'logo' => null
        ]);
    }

    #[Test]
    public function it_returns_validation_error_when_creating_with_invalid_data(): void
    {
        // Arrange: Siapkan data yang tidak valid (name kosong)
        $data = [
            'name' => '',
            'email_eo' => 'ini-bukan-email',
        ];

        // Act: Panggil endpoint store
        $response = $this->postJson(route('event-organizer.store'), $data);

        $response->dump();

        // Assert: Pastikan response adalah 422 Unprocessable Entity
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email_eo']);
    }

    //======================================================================
    // UPDATE METHOD TESTS
    //======================================================================

    #[Test]
    public function it_can_update_an_event_organizer()
    {
        // =======================================================================
        // Arrange: Membuat kondisi awal di DISK SUNGGUHAN
        // =======================================================================

        // 1. Tentukan path folder dan buat direktorinya secara fisik.
        $folderPath = storage_path('app/public/event-organizers/logo');
        File::makeDirectory($folderPath, 0755, true, true);

        // 2. Buat file "lama" dan pindahkan ke direktori fisik tersebut.
        $oldLogoFile = UploadedFile::fake()->image('old_logo.jpg');
        $oldLogoName = time() . '_old.' . $oldLogoFile->getClientOriginalExtension();
        $oldLogoFile->move($folderPath, $oldLogoName);
        $oldLogoDbPath = 'event-organizers/logo/' . $oldLogoName;

        // 3. Buat record Organizer di database yang menunjuk ke file "lama" yang ada.
        // Ini memastikan $organizer->logo tidak null, sehingga Trait tidak error.
        $organizer = EventOrganizer::factory()->create([
            'logo' => $oldLogoDbPath,
        ]);

        // 4. Siapkan file "baru" untuk request update.
        $newLogo = UploadedFile::fake()->image('new_logo.png');
        $updateData = [
            'name' => 'Updated EO Name',
            'description' => 'Updated description.',
            'logo' => $newLogo,
        ];

        // =======================================================================
        // Act: Kirim request ke controller
        // =======================================================================
        $response = $this->postJson(route('event-organizer.update', $organizer->id), array_merge($updateData, ['_method' => 'PUT']));

        // =======================================================================
        // Assert: Lakukan verifikasi pada hasil
        // =======================================================================
        $response->assertStatus(200);

        $this->assertDatabaseHas('event_organizers', [
            'id' => $organizer->id,
            'name' => 'Updated EO Name',
        ]);

        $updatedOrganizer = EventOrganizer::find($organizer->id);
        $newLogoDbPath = $updatedOrganizer->logo;

        // Gunakan fasad 'File' untuk memeriksa file sungguhan di disk.

        // Pastikan file BARU benar-benar ada.
        $this->assertTrue(
            File::exists(storage_path('app/public/' . $newLogoDbPath)),
            "File baru seharusnya ada di disk."
        );

        // Pastikan file LAMA sudah benar-benar terhapus.
        $this->assertFalse(
            File::exists(storage_path('app/public/' . $oldLogoDbPath)),
            "File lama seharusnya sudah terhapus oleh Trait."
        );
    }

    #[Test]
    public function it_returns_404_when_updating_a_non_existent_event_organizer()
    {
        // Arrange
        $updateData = ['name' => 'New Name'];

        // Act
        $response = $this->putJson(route('event-organizer.update', 999), $updateData);

        // Assert
        $response->assertStatus(404)
            ->assertJson(['message' => 'Event Organizer not found']);
    }
}
