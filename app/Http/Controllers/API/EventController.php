<?php

namespace App\Http\Controllers\API;

use Carbon\Carbon;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enum\Status\EventStatusEnum;
use App\Http\Controllers\Controller;
use App\Enum\Status\DocumentStatusEnum;
use Illuminate\Database\QueryException;
use App\Http\Controllers\BaseController;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EventController extends BaseController
{
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

            DB::beginTransaction();
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'end_date' => 'required|date|after_or_equal:start_date',
                'end_time' => 'required|date_format:H:i',
                'location' => 'required|string|max:255',
                'contact_phone' => 'required|string|max:20',
                'tnc_id' => 'required|exists:terms_and_cons,id',
                'is_accepted' => 'required|boolean|in:1',
                'facilities' => 'nullable|array|exists:facilities,id',
                'tickets' => 'nullable|array',
                'tickets.*.name' => 'required|string|max:255',
                'tickets.*.price' => 'required|numeric|min:0',
                'tickets.*.stock' => 'required|integer|min:0',
                'tickets.*.limit' => 'required|integer|min:1',
                'tickets.*.start_date' => 'required|date',
                'tickets.*.end_date' => 'required|date',
                'tickets.*.ticket_type_id' => 'required|exists:ticket_types,id',
            ]);


            if (!$validated['is_accepted']) {
                return $this->sendResponse(
                    [],
                    'You must agree to the terms and conditions to create an event.',
                    403
                );
            }

            return DB::transaction(function () use ($validated) {
                $event = Event::create([
                    'eo_id' => auth()->id(),
                    'name' => $validated['name'],
                    'description' => $validated['description'],
                    'start_date' => $validated['start_date'],
                    'start_time' => $validated['start_time'],
                    'end_date' => $validated['end_date'],
                    'end_time' => $validated['end_time'],
                    'location' => $validated['location'],
                    'status' => 'draft', // Default
                    // 'approval_status' => 'pending',
                    'contact_phone' => $validated['contact_phone'],
                    'tnc_id' => $validated['tnc_id'],
                    'is_accepted' => $validated['is_accepted'],
                    'is_published' => false,
                    'is_public' => false,
                ]);

                if (!empty($validated['facilities'])) {
                    $event->facilities()->sync($validated['facilities']);
                }

                if (!empty($validated['tickets'])) {
                    foreach ($validated['tickets'] as $ticketData) {
                        $event->tickets()->create($ticketData);
                    }
                }

                DB::commit();

                return response()->json([
                    'message' => 'Event created successfully',
                    'data' => $event->load(['facilities', 'tickets'])
                ], 201);
            });
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Database error in EventController@store: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create event due to database error',
            ], 500);
        } catch (HttpException $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Unexpected error in EventController@store: ' . $e->getMessage());
            return response()->json([
                'message' => 'An unexpected error occurred',
            ], 500);
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
