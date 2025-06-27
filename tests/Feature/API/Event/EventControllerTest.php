<?php

namespace Tests\Unit\Http\Controllers\API\Events;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Models\Facility;
use App\Models\TncStatus;
use App\Models\TermAndCon;
use App\Models\TicketType;
use App\Enum\Type\TncTypeEnum;
use App\Models\EventOrganizer;
use Spatie\Permission\Models\Role;
use App\Enum\Status\EventStatusEnum;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $organizer;
    protected $eventTnc;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Membuat Role yang dibutuhkan
        Role::findOrCreate('eo-owner');

        // 2. Membuat user dan memberikannya role 'eo-owner'
        $this->user = User::factory()->create([
            'name' => 'Test EO Owner',
            'email' => 'eo@test.com'
        ]);
        $this->user->assignRole('eo-owner'); // <-- PERBAIKAN: Assign role

        // Create event organizer
        $this->organizer = EventOrganizer::factory()->create([
            'eo_owner_id' => $this->user->id,
            'name' => 'Test Organizer',
            'organizer_type' => 'individual',
            'phone_no_eo' => '081234567890',
            'address_eo' => 'Jl. Test No. 123'
        ]);

        // Create event terms and conditions
        $this->eventTnc = TermAndCon::factory()->create([
            'type' => TncTypeEnum::EVENT->value,
            'content' => 'Test event terms and conditions'
        ]);

        // 3. Membuat TNC status untuk user
        // PERBAIKAN: Menghapus 'is_accepted' dan menggunakan 'agreed_at'
        TncStatus::factory()->create([
            'user_id' => $this->user->id,
            'tnc_id' => $this->eventTnc->id,
            'event_id' => null,
            'agreed_at' => now(), // <-- Controller mengecek keberadaan record ini
        ]);
    }

    /**
     * US14: Test creating draft event as EO Owner
     */
    #[Test]
    public function test_eo_owner_can_create_draft_event()
    {
        // Arrange
        $facilities = Facility::factory()->count(2)->create();
        $ticketType = TicketType::factory()->create();

        $eventData = [
            'name' => 'Test Event',
            'description' => 'This is a test event',
            'start_date' => '2025-07-01',
            'start_time' => '10:00',
            'end_date' => '2025-07-01',
            'end_time' => '18:00',
            'location' => 'Test Location',
            'contact_phone' => '081234567890',
            'tnc_id' => $this->eventTnc->id,
            'facilities' => $facilities->pluck('id')->toArray(),
            'tickets' => [
                [
                    'name' => 'Regular Ticket',
                    'price' => 100000,
                    'stock' => 100,
                    'limit' => 5,
                    'start_date' => '2025-06-01',
                    'end_date' => '2025-06-30',
                    'ticket_type_id' => $ticketType->id
                ]
            ]
        ];

        // Act
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('my-events.store'), $eventData);

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Event created successfully'
            ]);

        $this->assertDatabaseHas('events', [
            'name' => 'Test Event',
            'eo_id' => $this->organizer->id,
            'status' => 'draft',
            'is_published' => false,
            'is_public' => false
        ]);

        $event = Event::where('name', 'Test Event')->first();
        $this->assertCount(2, $event->facilities);
        $this->assertCount(1, $event->tickets);
    }

    // /**
    //  * US14: Test validation when creating draft event
    //  * @test
    //  */
    // public function test_create_event_validation_fails()
    // {
    //     // Act - Missing required fields
    //     $response = $this->actingAs($this->user, 'api')
    //         ->postJson('/api/events', []);

    //     // Assert
    //     $response->assertStatus(422)
    //         ->assertJson([
    //             'success' => false,
    //             'message' => 'Validation failed'
    //         ]);
    // }

    // /**
    //  * US14: Test creating event without accepting TNC fails
    //  * @test
    //  */
    // public function test_create_event_without_tnc_acceptance_fails()
    // {
    //     // Arrange - Remove TNC acceptance
    //     TncStatus::where('user_id', $this->user->id)->delete();

    //     $eventData = [
    //         'name' => 'Test Event',
    //         'start_date' => '2025-07-01',
    //         'start_time' => '10:00',
    //         'end_date' => '2025-07-01',
    //         'end_time' => '18:00',
    //         'location' => 'Test Location',
    //         'contact_phone' => '081234567890',
    //         'tnc_id' => $this->eventTnc->id,
    //     ];

    //     // Act
    //     $response = $this->actingAs($this->user, 'api')
    //         ->postJson('/api/events', $eventData);

    //     // Assert
    //     $response->assertStatus(403)
    //         ->assertJson([
    //             'success' => false,
    //             'message' => 'You must agree to the specified event terms and conditions to create an event.'
    //         ]);
    // }

    // /**
    //  * US15: Test publishing event successfully
    //  * @test
    //  */
    // public function test_eo_owner_can_publish_event()
    // {
    //     // Arrange - Create draft event
    //     $event = Event::factory()->create([
    //         'eo_id' => $this->organizer->id,
    //         'status' => EventStatusEnum::DRAFT,
    //         'is_published' => false
    //     ]);

    //     // Mock required documents method
    //     $this->organizer->shouldReceive('hasUploadedRequiredDocuments')
    //         ->andReturn(true);

    //     // Act
    //     $response = $this->actingAs($this->user, 'api')
    //         ->putJson("/api/events/{$event->id}/publish");

    //     // Assert
    //     $response->assertStatus(200)
    //         ->assertJson([
    //             'success' => true,
    //             'message' => 'Event published successfully.'
    //         ]);

    //     $this->assertDatabaseHas('events', [
    //         'id' => $event->id,
    //         'is_published' => true,
    //         'status' => EventStatusEnum::ACTIVE
    //     ]);
    // }

    // /**
    //  * US15: Test publishing event fails when organizer profile incomplete
    //  * @test
    //  */
    // public function test_publish_event_fails_with_incomplete_profile()
    // {
    //     // Arrange - Create organizer with incomplete profile
    //     $incompleteOrganizer = EventOrganizer::factory()->create([
    //         'eo_owner_id' => $this->user->id,
    //         'phone_no_eo' => '0000', // Placeholder value
    //         'address_eo' => 'Alamat belum diisi' // Placeholder value
    //     ]);

    //     $event = Event::factory()->create([
    //         'eo_id' => $incompleteOrganizer->id,
    //         'status' => EventStatusEnum::DRAFT,
    //         'is_published' => false
    //     ]);

    //     // Act
    //     $response = $this->actingAs($this->user, 'api')
    //         ->putJson("/api/events/{$event->id}/publish");

    //     // Assert
    //     $response->assertStatus(422)
    //         ->assertJson([
    //             'success' => false,
    //             'message' => 'Please complete your Event Organizer profile (address, phone number, etc.) before publishing.',
    //             'data' => [
    //                 'action_required' => 'UPDATE_EO_PROFILE'
    //             ]
    //         ]);
    // }

    // /**
    //  * US15: Test publishing event fails when documents not uploaded
    //  * @test
    //  */
    // public function test_publish_event_fails_without_required_documents()
    // {
    //     // Arrange
    //     $event = Event::factory()->create([
    //         'eo_id' => $this->organizer->id,
    //         'status' => EventStatusEnum::DRAFT,
    //         'is_published' => false
    //     ]);

    //     // Mock required documents method to return false
    //     $this->organizer->shouldReceive('hasUploadedRequiredDocuments')
    //         ->andReturn(false);

    //     // Act
    //     $response = $this->actingAs($this->user, 'api')
    //         ->putJson("/api/events/{$event->id}/publish");

    //     // Assert
    //     $response->assertStatus(422)
    //         ->assertJson([
    //             'success' => false,
    //             'message' => 'Please upload all required documents for your profile (e.g., KTP for Individual) before publishing.',
    //             'data' => [
    //                 'action_required' => 'UPLOAD_DOCUMENTS'
    //             ]
    //         ]);
    // }

    // /**
    //  * US15: Test unauthorized user cannot publish event
    //  * @test
    //  */
    // public function test_unauthorized_user_cannot_publish_event()
    // {
    //     // Arrange
    //     $otherUser = User::factory()->create();
    //     $event = Event::factory()->create([
    //         'eo_id' => $this->organizer->id,
    //         'status' => EventStatusEnum::DRAFT,
    //         'is_published' => false
    //     ]);

    //     // Act
    //     $response = $this->actingAs($otherUser, 'api')
    //         ->putJson("/api/events/{$event->id}/publish");

    //     // Assert
    //     $response->assertStatus(403)
    //         ->assertJson([
    //             'success' => false,
    //             'message' => 'You are not authorized to publish this event.'
    //         ]);
    // }

    // /**
    //  * US15: Test cannot publish already published event
    //  * @test
    //  */
    // public function test_cannot_publish_already_published_event()
    // {
    //     // Arrange
    //     $event = Event::factory()->create([
    //         'eo_id' => $this->organizer->id,
    //         'status' => EventStatusEnum::ACTIVE,
    //         'is_published' => true
    //     ]);

    //     // Act
    //     $response = $this->actingAs($this->user, 'api')
    //         ->putJson("/api/events/{$event->id}/publish");

    //     // Assert
    //     $response->assertStatus(400)
    //         ->assertJson([
    //             'success' => false,
    //             'message' => 'Event is already published.'
    //         ]);
    // }

    // /**
    //  * Test EO can view their own events
    //  * @test
    //  */
    // public function test_eo_owner_can_view_their_events()
    // {
    //     // Arrange
    //     Event::factory()->count(3)->create([
    //         'eo_id' => $this->organizer->id
    //     ]);

    //     // Act
    //     $response = $this->actingAs($this->user, 'api')
    //         ->getJson('/api/events');

    //     // Assert
    //     $response->assertStatus(200)
    //         ->assertJson([
    //             'success' => true,
    //             'message' => 'My events retrieved successfully.'
    //         ])
    //         ->assertJsonCount(3, 'data.data');
    // }

    // /**
    //  * Test EO can view specific event detail
    //  * @test
    //  */
    // public function test_eo_owner_can_view_event_detail()
    // {
    //     // Arrange
    //     $event = Event::factory()->create([
    //         'eo_id' => $this->organizer->id,
    //         'name' => 'Test Event Detail'
    //     ]);

    //     // Act
    //     $response = $this->actingAs($this->user, 'api')
    //         ->getJson("/api/events/{$event->id}");

    //     // Assert
    //     $response->assertStatus(200)
    //         ->assertJson([
    //             'success' => true,
    //             'message' => 'Event retrieved successfully.',
    //             'data' => [
    //                 'name' => 'Test Event Detail'
    //             ]
    //         ]);
    // }

    // /**
    //  * Test EO cannot view other organizer's events
    //  * @test
    //  */
    // public function test_eo_owner_cannot_view_other_organizer_events()
    // {
    //     // Arrange
    //     $otherUser = User::factory()->create();
    //     $otherOrganizer = EventOrganizer::factory()->create([
    //         'eo_owner_id' => $otherUser->id
    //     ]);
    //     $event = Event::factory()->create([
    //         'eo_id' => $otherOrganizer->id
    //     ]);

    //     // Act
    //     $response = $this->actingAs($this->user, 'api')
    //         ->getJson("/api/events/{$event->id}");

    //     // Assert
    //     $response->assertStatus(404)
    //         ->assertJson([
    //             'success' => false,
    //             'message' => 'Event not found.'
    //         ]);
    // }

    // /**
    //  * Test updating draft event
    //  * @test
    //  */
    // public function test_eo_owner_can_update_draft_event()
    // {
    //     // Arrange
    //     $event = Event::factory()->create([
    //         'eo_id' => $this->organizer->id,
    //         'status' => EventStatusEnum::DRAFT,
    //         'name' => 'Original Name'
    //     ]);

    //     $updateData = [
    //         'name' => 'Updated Event Name',
    //         'description' => 'Updated description'
    //     ];

    //     // Act
    //     $response = $this->actingAs($this->user, 'api')
    //         ->putJson("/api/events/{$event->id}", $updateData);

    //     // Assert
    //     $response->assertStatus(200)
    //         ->assertJson([
    //             'success' => true,
    //             'message' => 'Event updated successfully'
    //         ]);

    //     $this->assertDatabaseHas('events', [
    //         'id' => $event->id,
    //         'name' => 'Updated Event Name',
    //         'description' => 'Updated description'
    //     ]);
    // }

    // /**
    //  * Test cannot update published event
    //  * @test
    //  */
    // public function test_cannot_update_published_event()
    // {
    //     // Arrange
    //     $event = Event::factory()->create([
    //         'eo_id' => $this->organizer->id,
    //         'status' => EventStatusEnum::ACTIVE
    //     ]);

    //     // Act
    //     $response = $this->actingAs($this->user, 'api')
    //         ->putJson("/api/events/{$event->id}", ['name' => 'New Name']);

    //     // Assert
    //     $response->assertStatus(403)
    //         ->assertJson([
    //             'message' => 'Only draft events can be updated'
    //         ]);
    // }

    // /**
    //  * Test deleting event
    //  * @test
    //  */
    // public function test_eo_owner_can_delete_event()
    // {
    //     // Arrange
    //     $event = Event::factory()->create([
    //         'eo_id' => $this->organizer->id
    //     ]);

    //     // Act
    //     $response = $this->actingAs($this->user, 'api')
    //         ->deleteJson("/api/events/{$event->id}");

    //     // Assert
    //     $response->assertStatus(200)
    //         ->assertJson([
    //             'message' => 'Event deleted successfully'
    //         ]);

    //     $this->assertDatabaseMissing('events', [
    //         'id' => $event->id
    //     ]);
    // }

    // /**
    //  * Test toggling event public status
    //  * @test
    //  */
    // public function test_eo_owner_can_toggle_event_public_status()
    // {
    //     // Arrange
    //     $event = Event::factory()->create([
    //         'eo_id' => $this->organizer->id,
    //         'is_published' => true,
    //         'is_public' => false
    //     ]);

    //     // Act
    //     $response = $this->actingAs($this->user, 'api')
    //         ->putJson("/api/events/{$event->id}/public-status");

    //     // Assert
    //     $response->assertStatus(200)
    //         ->assertJson([
    //             'success' => true,
    //             'message' => 'Event visibility has been successfully changed to Public'
    //         ]);

    //     $this->assertDatabaseHas('events', [
    //         'id' => $event->id,
    //         'is_public' => true
    //     ]);
    // }

    // /**
    //  * Test cannot toggle public status of unpublished event
    //  * @test
    //  */
    // public function test_cannot_toggle_public_status_of_unpublished_event()
    // {
    //     // Arrange
    //     $event = Event::factory()->create([
    //         'eo_id' => $this->organizer->id,
    //         'is_published' => false
    //     ]);

    //     // Act
    //     $response = $this->actingAs($this->user, 'api')
    //         ->putJson("/api/events/{$event->id}/public-status");

    //     // Assert
    //     $response->assertStatus(422)
    //         ->assertJson([
    //             'success' => false,
    //             'message' => 'Only published events can be made public or private.'
    //         ]);
    // }
}
