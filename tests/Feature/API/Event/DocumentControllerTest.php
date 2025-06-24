<?php

namespace Tests\Feature\API\Event;

use Tests\TestCase;
use App\Models\User;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use App\Models\EventOrganizer;
use Spatie\Permission\Models\Role;
use App\Enum\Status\DocumentStatusEnum;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use App\Notifications\DocumentStatusUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\File;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $eoUser;
    protected User $anotherEoUser;
    protected User $superAdminUser;
    protected EventOrganizer $eventOrganizer;

    public function setUp(): void
    {
        parent::setUp();

        // Buat roles
        Role::create(['name' => 'super-admin', 'guard_name' => 'api']);
        Role::create(['name' => 'eo-owner', 'guard_name' => 'api']);

        // Buat user & profil EO utama untuk testing
        $this->eoUser = User::factory()->create()->assignRole('eo-owner');
        $this->eventOrganizer = EventOrganizer::factory()->create(['eo_owner_id' => $this->eoUser->id]);

        // Buat user super-admin
        $this->superAdminUser = User::factory()->create()->assignRole('super-admin');

        // Buat user EO kedua untuk test otorisasi
        $this->anotherEoUser = User::factory()->create()->assignRole('eo-owner');
    }

    protected function tearDown(): void
    {
        if (File::exists(storage_path('app/public/documents'))) {
            File::deleteDirectory(storage_path('app/public/documents'));
        }
        parent::tearDown();
    }


    //==================================
    // STORE METHOD TESTS
    //==================================

    #[Test]
    public function an_eo_owner_can_upload_a_document_for_their_eo(): void
    {
        // Arrange
        $this->actingAs($this->eoUser, 'sanctum');

        // Siapkan data request, TERMASUK event_organizer_id
        $data = [
            'event_organizer_id' => $this->eventOrganizer->id,
            'type' => 'ktp',
            'file' => UploadedFile::fake()->image('ktp.jpg'),
            'number' => '3201234567890001',
            'name' => $this->eoUser->name,
            'address' => $this->faker->address,
        ];

        // Buat direktori tujuan secara manual agar ->move() di Trait berhasil
        File::makeDirectory(storage_path('app/public/documents/eo_' . $this->eventOrganizer->id), 0755, true, true);

        // Act
        $response = $this->postJson(route('documents.store'), $data);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('documents', [
            'documentable_id' => $this->eventOrganizer->id,
            'type' => 'ktp',
        ]);

        $document = Document::first();
        $this->assertTrue(File::exists(storage_path('app/public/' . $document->file)));
    }

    #[Test]
    public function an_eo_owner_cannot_upload_for_another_owners_eo(): void
    {
        // Arrange
        $anotherOrganizer = EventOrganizer::factory()->create(['eo_owner_id' => $this->anotherEoUser->id]);
        $this->actingAs($this->eoUser, 'sanctum'); // Login sebagai user pertama

        $data = [
            'event_organizer_id' => $anotherOrganizer->id, // Coba upload untuk EO milik orang lain
            'type' => 'ktp',
            'file' => UploadedFile::fake()->image('ktp.jpg'),
            'number' => '12345',
            'name' => 'test',
            'address' => 'test',
        ];

        // Act
        $response = $this->postJson(route('documents.store'), $data);

        // Assert
        $response->assertStatus(403); // Forbidden
    }

    #[Test]
    public function store_fails_if_document_type_already_exists(): void
    {
        // Arrange: Buat dokumen KTP untuk EO ini
        Document::factory()->for($this->eventOrganizer, 'documentable')->forKtp()->create();
        $this->actingAs($this->eoUser, 'sanctum');

        $data = [
            'event_organizer_id' => $this->eventOrganizer->id,
            'type' => 'ktp', // Coba upload KTP lagi
            'file' => UploadedFile::fake()->image('ktp_kedua.jpg'),
            'number' => '98765',
            'name' => 'test',
            'address' => 'test',
        ];

        // Act
        $response = $this->postJson(route('documents.store'), $data);

        // Assert
        $response->assertStatus(409); // Conflict
        $this->assertDatabaseCount('documents', 1);
    }

    #[Test]
    public function store_fails_on_validation_error(): void
    {
        $this->actingAs($this->eoUser, 'sanctum');
        $invalidData = ['event_organizer_id' => $this->eventOrganizer->id, 'type' => 'surat_cinta'];
        $response = $this->postJson(route('documents.store'), $invalidData);
        $response->assertStatus(422)->assertJsonValidationErrors(['type', 'file', 'number']);
    }

    //==================================
    // INDEX, SHOW, & UPDATE STATUS TESTS
    //==================================

    #[Test]
    public function an_admin_can_get_list_of_pending_documents_by_default(): void
    {
        Document::factory()->count(2)->create(['status' => DocumentStatusEnum::PENDING]);
        Document::factory()->count(3)->create(['status' => DocumentStatusEnum::VERIFIED]);
        $this->actingAs($this->superAdminUser, 'sanctum');
        $response = $this->getJson(route('documents.index'));
        $response->assertStatus(200)->assertJsonCount(2, 'data.data');
    }

    #[Test]
    public function an_admin_can_show_a_single_document(): void
    {
        $document = Document::factory()->create();
        $this->actingAs($this->superAdminUser, 'sanctum');
        $response = $this->getJson(route('documents.show', $document->id));
        $response->assertStatus(200)->assertJsonPath('data.id', $document->id);
    }

    #[Test]
    public function an_admin_can_update_document_status_to_verified_and_notifies_user(): void
    {
        Notification::fake();
        $document = Document::factory()->for($this->eventOrganizer, 'documentable')->create(['status' => DocumentStatusEnum::PENDING]);
        $this->actingAs($this->superAdminUser, 'sanctum');

        $response = $this->patchJson(route('documents.updateStatus', $document->id), [
            'status' => DocumentStatusEnum::VERIFIED->value
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => DocumentStatusEnum::VERIFIED->value]);
        Notification::assertSentTo($this->eoUser, DocumentStatusUpdated::class);
    }

    // --- TEST BARU DITAMBAHKAN DI SINI ---

    #[Test]
    public function an_admin_can_update_document_status_to_rejected_with_reason(): void
    {
        // Arrange
        Notification::fake();
        $document = Document::factory()->for($this->eventOrganizer, 'documentable')->create(['status' => DocumentStatusEnum::PENDING]);
        $this->actingAs($this->superAdminUser, 'sanctum');
        $reason = 'Foto KTP buram dan tidak terbaca.';

        // Act
        $response = $this->patchJson(route('documents.updateStatus', $document->id), [
            'status' => DocumentStatusEnum::REJECTED->value,
            'reason_rejected' => $reason
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.status', DocumentStatusEnum::REJECTED->value)
            ->assertJsonPath('data.reason_rejected', $reason);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => DocumentStatusEnum::REJECTED->value,
            'reason_rejected' => $reason
        ]);

        Notification::assertSentTo($this->eoUser, DocumentStatusUpdated::class);
    }

    #[Test]
    public function update_status_to_rejected_fails_without_a_reason(): void
    {
        // Arrange
        $document = Document::factory()->create(['status' => DocumentStatusEnum::PENDING]);
        $this->actingAs($this->superAdminUser, 'sanctum');

        // Act: Coba reject tanpa menyertakan 'reason_rejected'
        $response = $this->patchJson(route('documents.updateStatus', $document->id), [
            'status' => DocumentStatusEnum::REJECTED->value
        ]);

        // Assert
        $response->assertStatus(422) // Unprocessable Entity (Error Validasi)
            ->assertJsonValidationErrors(['reason_rejected']);
    }

    #[Test]
    public function a_non_admin_cannot_update_document_status(): void
    {
        // Arrange
        $document = Document::factory()->create(['status' => DocumentStatusEnum::PENDING]);
        // Login sebagai EO Owner, bukan sebagai Super Admin
        $this->actingAs($this->eoUser, 'sanctum');

        // Act: Coba ubah status
        $response = $this->patchJson(route('documents.updateStatus', $document->id), [
            'status' => DocumentStatusEnum::VERIFIED->value
        ]);

        // Assert
        // Aksi ini seharusnya diblokir oleh middleware 'role:super-admin'
        $response->assertStatus(403); // Forbidden
    }
}
