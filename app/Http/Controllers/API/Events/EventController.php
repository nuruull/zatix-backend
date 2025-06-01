<?php

namespace App\Http\Controllers\API\Events;

use Carbon\Carbon;
use App\Models\Event;
use App\Models\TncStatus;
use App\Models\TermAndCon;
use Illuminate\Http\Request;
use App\Traits\ManageFileTrait;
use App\Enum\Type\TncTypeEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enum\Status\EventStatusEnum;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Enum\Status\DocumentStatusEnum;
use Illuminate\Database\QueryException;
use App\Http\Controllers\BaseController;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EventController extends BaseController
{
    use ManageFileTrait;
    public function index()
    {
        $events = Event::with(['facilities', 'tickets'])->get();
        return response()->json($events);
    }

    public function show($id)
    {
        $event = Event::with(['facilities', 'tickets'])->find($id);
        if (!$event) {
            return response()->json(['message' => 'Event not found'], 404);
        }
        return response()->json($event);
    }

    public function store(Request $request)
    {
        try {
            // DB::beginTransaction();
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'end_date' => 'required|date|after_or_equal:start_date',
                'end_time' => 'required|date_format:H:i',
                'location' => 'required|string|max:255',
                'contact_phone' => 'required|string|max:20',
                'tnc_id' => 'required|exists:terms_and_cons,id',
                'facilities' => 'nullable|array',
                'facilities.*' => 'exists:facilities,id',
                'tickets' => 'nullable|array',
                'tickets.*.name' => 'required|string|max:255',
                'tickets.*.price' => 'required|numeric|min:0',
                'tickets.*.stock' => 'required|integer|min:0',
                'tickets.*.limit' => 'required|integer|min:1',
                'tickets.*.start_date' => 'required|date',
                'tickets.*.end_date' => 'required|date',
                'tickets.*.ticket_type_id' => 'required|exists:ticket_types,id',
            ]);

            $user = Auth::user();
            $eventOrganizerEntity = $user->eventOrganizer;

            if (!$eventOrganizerEntity) {
                return $this->sendError(
                    'User is not associated with a valid Event Organizer entity.',
                    [],
                    403
                );
            }
            $tncFromRequest = $validatedData['tnc_id'];

            $eventTnc = TermAndCon::where('id', $tncFromRequest)
                ->where('type', TncTypeEnum::EVENT->value)
                ->first();

            if (!$eventTnc) {
                return $this->sendError(
                    'Invalid event terms and conditions specified.',
                    [],
                    422
                );
            }

            $hasAcceptedTnc = $user->tncStatuses()
                ->where('tnc_id', $eventTnc->id)
                ->exists();

            if (!$hasAcceptedTnc) {
                return $this->sendError(
                    'You must agree to the specified event terms and conditions to create an event.',
                    [],
                    403
                );
            }

            $event = DB::transaction(function () use ($validatedData, $user, $eventTnc, $eventOrganizerEntity) {
                $createdEvent = Event::create([
                    'eo_id' => $eventOrganizerEntity->id,
                    'name' => $validatedData['name'],
                    'description' => $validatedData['description'],
                    'start_date' => $validatedData['start_date'],
                    'start_time' => $validatedData['start_time'],
                    'end_date' => $validatedData['end_date'],
                    'end_time' => $validatedData['end_time'],
                    'location' => $validatedData['location'],
                    'status' => 'draft',
                    'contact_phone' => $validatedData['contact_phone'],
                    'tnc_id' => $eventTnc->id,
                    // 'is_accepted' => true,
                    'is_published' => false,
                    'is_public' => false,
                ]);

                if (!empty($validatedData['facilities'])) {
                    $createdEvent->facilities()->sync($validatedData['facilities']);
                }

                if (!empty($validatedData['tickets'])) {
                    foreach ($validatedData['tickets'] as $ticketData) {
                        $createdEvent->tickets()->create($ticketData);
                    }
                }

                TncStatus::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'tnc_id' => $eventTnc->id,
                        'event_id' => $createdEvent->id
                    ],
                    [
                        'accepted_at' => now(),
                        'event_id' => $createdEvent->id
                    ]
                );

                return $createdEvent->load(['facilities', 'tickets']);
            });

            return $this->sendResponse(
                $event,
                'Event created successfully',
                201
            );

        } catch (ValidationException $e) {
            // DB::rollBack();
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (QueryException $e) {
            // DB::rollBack();
            Log::error('Database error in EventController@store: ' . $e->getMessage(), ['sql' => $e->getSql(), 'bindings' => $e->getBindings()]);
            return $this->sendError('Failed to create event due to a database error.', [], 500);
        } catch (\Exception $e) {
            // DB::rollBack();
            Log::error('Unexpected error in EventController@store: ' . $e->getMessage(), ["trace" => $e->getTraceAsString()]);
            return $this->sendError('An unexpected error occurred while creating the event.', [], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $event = Event::find($id);

            if (!$event) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            if ($event->eo_id !== auth()->id()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            if ($event->status !== EventStatusEnum::DRAFT) {
                return response()->json(['message' => 'Only draft events can be updated'], 403);
            }

            DB::beginTransaction();
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'sometimes|required|date',
                'start_time' => 'sometimes|required|date_format:H:i',
                'end_date' => 'sometimes|required|date|after_or_equal:start_date',
                'end_time' => 'sometimes|required|date_format:H:i',
                'location' => 'sometimes|required|string|max:255',
                'contact_phone' => 'sometimes|required|string|max:20',
                'facilities' => 'nullable|array|exists:facilities,id',
                'tickets' => 'nullable|array',
                'tickets.*.id' => 'sometimes|required|exists:tickets,id',
                'tickets.*.name' => 'required_with:tickets|string|max:255',
                'tickets.*.price' => 'required_with:tickets|numeric|min:0',
                'tickets.*.stock' => 'required_with:tickets|integer|min:0',
                'tickets.*.limit' => 'required_with:tickets|integer|min:1',
                'tickets.*.start_date' => 'required_with:tickets|date|before_or_equal:start_date',
                'tickets.*.end_date' => 'required_with:tickets|date|before_or_equal:start_date',
                'tickets.*.ticket_type_id' => 'required_with:tickets|exists:ticket_types,id',
            ]);

            $event = Event::findOrFail($id);

            return DB::transaction(function () use ($validated, $event) {
                // Update data dasar event
                $event->update([
                    'name' => $validated['name'] ?? $event->name,
                    'description' => $validated['description'] ?? $event->description,
                    'start_date' => $validated['start_date'] ?? $event->start_date,
                    'start_time' => $validated['start_time'] ?? $event->start_time,
                    'end_date' => $validated['end_date'] ?? $event->end_date,
                    'end_time' => $validated['end_time'] ?? $event->end_time,
                    'location' => $validated['location'] ?? $event->location,
                    'contact_phone' => $validated['contact_phone'] ?? $event->contact_phone,
                ]);

                // Sync facilities jika ada
                if (array_key_exists('facilities', $validated)) {
                    $event->facilities()->sync($validated['facilities'] ?? []);
                }

                // Handle tickets
                if (!empty($validated['tickets'])) {
                    foreach ($validated['tickets'] as $ticketData) {
                        if (isset($ticketData['id'])) {
                            // Update existing ticket
                            $ticket = $event->tickets()->findOrFail($ticketData['id']);
                            $ticket->update($ticketData);
                        } else {
                            // Create new ticket
                            $event->tickets()->create($ticketData);
                        }
                    }
                }

                return response()->json($event->fresh(), 200);
            });
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Event not found'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            Log::error('Database error in EventController@update: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update event due to database error',
            ], 500);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'You are not authorized to update this event',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Unexpected error in EventController@update: ' . $e->getMessage());
            return response()->json([
                'message' => 'An unexpected error occurred',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $event = Event::with(['facilities', 'tickets'])->find($id);

            if (!$event) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            $event->facilities()->detach();

            $event->tickets()->delete();

            $event->delete();

            return response()->json(['message' => 'Event deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong while deleting the event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function publish($id)
    {
        try {
            $event = Event::with('eventOrganizer.documentType')->find($id);

            if (!$event) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            if ($event->eo_id !== auth()->id()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            if ($event->is_published) {
                return response()->json(['message' => 'Event already published'], 400);
            }

            $eo = $event->eventOrganizer;

            $missingFields = [];
            foreach (['email_eo', 'phone_no_eo', 'address_eo', 'description'] as $field) {
                if (empty($eo->$field))
                    $missingFields[] = $field;
            }

            if (!empty($missingFields)) {
                return response()->json([
                    'message' => 'Lengkapi data EO terlebih dahulu',
                    'missing_fields' => $missingFields
                ], 422);
            }

            $documentType = $eo->documentType;

            if (!$documentType || $documentType->status !== DocumentStatusEnum::VERIFIED) {
                return response()->json([
                    'message' => 'Dokumen legal belum diverifikasi'
                ], 422);
            }

            $event->update([
                'is_published' => true,
                'status' => EventStatusEnum::ACTIVE
            ]);
            return response()->json(['message' => 'Event published successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to publish event: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to publish event'], 500);
        }
    }
}
