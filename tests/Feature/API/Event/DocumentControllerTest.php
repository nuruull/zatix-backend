<?php

namespace Tests\Feature\API\Event;

use Tests\TestCase;
use App\Models\User;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use App\Models\EventOrganizer;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Storage;
use App\Enum\Status\DocumentStatusEnum;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use App\Notifications\DocumentStatusUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Notifications\NewVerificationRequest;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\File;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $eoUser;
    protected User $superAdminUser;
    protected EventOrganizer $eventOrganizer;

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/public/documents'));
        parent::tearDown();
    }

    public function setUp(): void
    {
        parent::setUp();

        // Buat role super-admin
        Role::create(['name' => 'super-admin', 'guard_name' => 'api']);
        Role::create(['name' => 'eo-owner', 'guard_name' => 'api']);

        // Buat user super-admin
        $this->superAdminUser = User::factory()->create();
        $this->superAdminUser->assignRole('super-admin');

        // Buat user pemilik EO
        $this->eoUser = User::factory()->create();
        $this->eventOrganizer = EventOrganizer::factory()->create([
            'eo_owner_id' => $this->eoUser->id,
        ]);

        // Buat user pemilik EO dan EO-nya
        $this->eoUser = User::factory()->create();
        $this->eventOrganizer = EventOrganizer::factory()->create([
            'eo_owner_id' => $this->eoUser->id,
        ]);
        $this->eoUser->assignRole('eo-owner');
    }

    //==================================
    // STORE METHOD TESTS
    //==================================

    #[Test]
    public function can_store_document_successfully_and_notifies_admin(): void
    {
        Notification::fake();

        // Lakukan otentikasi (pilih salah satu, withToken lebih disarankan)
        $this->actingAs($this->eoUser, 'sanctum');
        // $token = $this->eoUser->createToken('test-token')->plainTextToken;

        // 2. BUAT DIREKTORI TUJUAN SECARA MANUAL DI DISK SUNGGUHAN
        $folder = 'documents/eo_' . $this->eventOrganizer->id . 'type/_ktp';
        File::makeDirectory(storage_path('app/public/' . $folder), 0755, true, true);

        // Siapkan data request
        $data = [
            'type' => 'ktp',
            'file' => UploadedFile::fake()->image('ktp.jpg'),
            'number' => '3201234567890001',
            'name' => $this->eoUser->name,
            'address' => $this->faker->address,
        ];

        // Act: Kirim request
        $response = $this->postJson(route('document.store'), $data);
        // Jika pakai token: $this->withToken($token)->postJson(...)

        // Assert: Pastikan response sukses
        $response->assertStatus(201);

        // Assert: Pastikan data tersimpan di database
        $this->assertDatabaseHas('documents', [
            'documentable_id' => $this->eventOrganizer->id,
            'type' => 'ktp',
        ]);

        // 3. GUNAKAN `File::exists()` UNTUK MEMERIKSA FILE DI DISK SUNGGUHAN
        $document = Document::first();
        $this->assertTrue(
            File::exists(storage_path('app/public/' . $document->file)),
            "File seharusnya ada di disk sungguhan pada path: " . $document->file
        );

        // Assert notifikasi
        Notification::assertSentTo($this->superAdminUser, NewVerificationRequest::class);
    }

    #[Test]
    public function store_fails_if_user_has_no_event_organizer(): void
    {
        // Arrange: Buat user baru, berikan role, tapi JANGAN buat Event Organizer untuknya.
        $userWithoutEo = User::factory()->create();
        $userWithoutEo->assignRole('eo-owner'); // Berikan role agar lolos middleware

        // Gunakan guard 'sanctum' untuk otentikasi
        $this->actingAs($userWithoutEo, 'sanctum');

        // Siapkan data dummy untuk dikirim
        $data = [
            'type' => 'ktp',
            'file' => UploadedFile::fake()->image('ktp.jpg'),
            'number' => '12345',
            'name' => 'Test User',
            'address' => 'Test Address',
        ];

        // Act: Kirim request
        $response = $this->postJson(route('document.store'), $data);

        // Assert: Sekarang request akan lolos middleware dan masuk ke controller,
        // di mana ia akan gagal di `if (!$eventOrganizer)` dan mengembalikan 403.
        $response->assertStatus(403)
            ->assertJsonPath('message', 'Event Organizer is invalid or not found for this user.');
    }

    #[Test]
    public function store_fails_on_validation_error(): void
    {
        // Arrange
        // Menggunakan guard 'sanctum' agar sesuai dengan middleware route Anda
        $this->actingAs($this->eoUser, 'sanctum'); // <-- Penyesuaian penting di sini

        $invalidData = [
            'type' => 'surat_cinta', // Tipe tidak valid
            'file' => UploadedFile::fake()->create('document.txt', 100, 'text/plain'), // Mime tidak valid
        ];

        // Act
        // Pastikan nama route sudah benar (biasanya plural)
        $response = $this->postJson(route('document.store'), $invalidData);

        // Assert
        $response->assertStatus(422) // Mengharapkan error validasi
            ->assertJsonValidationErrors(['type', 'file', 'number', 'name', 'address']);
    }

    //==================================
    // INDEX METHOD TESTS
    //==================================

    #[Test]
    public function can_get_list_of_pending_documents_by_default(): void
    {
        // Arrange
        Document::factory()->count(2)->create(['status' => DocumentStatusEnum::PENDING]);
        // Saya ganti menjadi APPROVED agar konsisten dengan tes lain
        Document::factory()->count(3)->create(['status' => DocumentStatusEnum::VERIFIED]);

        // Gunakan guard 'sanctum' agar otentikasi berhasil
        $this->actingAs($this->superAdminUser, 'sanctum'); // <-- Penyesuaian penting di sini

        // Act
        $response = $this->getJson(route('document.index'));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.data'); // Cek di dalam data paginasi
    }

    #[Test]
    public function can_filter_documents_by_status(): void
    {
        // Arrange
        Document::factory()->count(2)->create(['status' => DocumentStatusEnum::PENDING]);
        // Saya ubah menjadi APPROVED agar konsisten dengan tes lainnya
        Document::factory()->count(3)->create(['status' => DocumentStatusEnum::VERIFIED]);

        // Gunakan guard 'sanctum' agar otentikasi berhasil
        $this->actingAs($this->superAdminUser, 'sanctum'); // <-- Penyesuaian penting di sini

        // Act
        // Sesuaikan nilai filter dengan status yang dibuat
        $response = $this->getJson(route('document.index', ['status' => 'verified']));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    }

    #[Test]
    public function returns_error_for_invalid_status_filter(): void
    {
        // Arrange
        $this->actingAs($this->superAdminUser, 'sanctum');

        // Act
        $response = $this->getJson(route('document.index', ['status' => 'invalid_status']));

        // Assert
        $response->assertStatus(400);
    }

    //==================================
    // SHOW METHOD TESTS
    //==================================

    #[Test]
    public function can_show_a_single_document(): void
    {
        // Arrange
        $document = Document::factory()->create();
        $this->actingAs($this->superAdminUser, 'sanctum');

        // Act
        $response = $this->getJson(route('document.show', $document->id));

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.id', $document->id);
    }

    //==================================
    // UPDATE STATUS METHOD TESTS
    //==================================

    #[Test]
    public function can_update_document_status_to_verified_and_notifies_user(): void
    {
        // Arrange
        Notification::fake();
        $document = Document::factory()->create([
            'documentable_id' => $this->eventOrganizer->id,
            'documentable_type' => EventOrganizer::class,
            'status' => DocumentStatusEnum::PENDING,
        ]);
        $this->actingAs($this->superAdminUser, 'sanctum');

        // Act
        $response = $this->patchJson(route('document.updateStatus', $document->id), [
            'status' => DocumentStatusEnum::VERIFIED->value
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.status', DocumentStatusEnum::VERIFIED->value);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => DocumentStatusEnum::VERIFIED->value
        ]);

        // Assert
        Notification::assertSentTo($this->eoUser, DocumentStatusUpdated::class);
    }

    #[Test]
    public function can_update_document_status_to_rejected_with_reason(): void
    {
        // Arrange
        Notification::fake();
        $document = Document::factory()->create([
            'documentable_id' => $this->eventOrganizer->id,
            'documentable_type' => EventOrganizer::class,
            'status' => DocumentStatusEnum::PENDING,
        ]);
        $this->actingAs($this->superAdminUser, 'sanctum');

        // Act
        $response = $this->patchJson(route('document.updateStatus', $document->id), [
            'status' => DocumentStatusEnum::REJECTED->value,
            'reason_rejected' => 'KTP tidak terbaca.'
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.status', DocumentStatusEnum::REJECTED->value)
            ->assertJsonPath('data.reason_rejected', 'KTP tidak terbaca.');

        Notification::assertSentTo($this->eoUser, DocumentStatusUpdated::class);
    }

    #[Test]
    public function update_status_fails_if_rejected_without_reason(): void
    {
        // Arrange
        $document = Document::factory()->create();
        $this->actingAs($this->superAdminUser, 'sanctum');

        // Act
        $response = $this->patchJson(route('document.updateStatus', $document->id), [
            'status' => DocumentStatusEnum::REJECTED->value
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason_rejected']);
    }
}
