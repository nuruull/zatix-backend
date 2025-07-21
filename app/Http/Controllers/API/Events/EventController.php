<?php

namespace App\Http\Controllers\API\Events;

use App\Http\Resources\EventResource;
use Carbon\Carbon;
use App\Models\Event;
use App\Models\TncStatus;
use App\Models\TermAndCon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Enum\Type\TncTypeEnum;
use App\Traits\ManageFileTrait;
use Illuminate\Validation\Rule;
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
    public function index(Request $request)
    {
        $user = Auth::user();
        $organizer = $user->eventOrganizer;

        if (!$organizer) {
            return $this->sendResponse([], 'User does not have an Event Organizer profile.');
        }

        $events = $organizer->events()
            ->with(['facilities', 'tickets'])
            ->latest()
            ->paginate($request->input('per_page', 15));

        return $this->sendResponse($events, 'My events retrieved successfully.');
    }

    public function show(Event $event)
    {
        if (Auth::id() !== $event->eventOrganizer->eo_owner_id) {
            return $this->sendError('Event not found.', [], 404);
        }

        $event->load(['facilities', 'tickets', 'eventOrganizer']);
        return $this->sendResponse($event, 'Event retrieved successfully.');
    }

    public function store(Request $request)
    {
        try {
            // DB::beginTransaction();
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'poster' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
                'start_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'end_date' => 'required|date|after_or_equal:start_date',
                'end_time' => 'required|date_format:H:i',
                'location' => 'required|string|max:255',
                'contact_phone' => 'required|string|max:20',
                'tnc_id' => [
                    'required',
                    Rule::exists('terms_and_cons', 'id')->where(function ($query) {
                        $query->where('type', TncTypeEnum::EVENT->value);
                    })
                ],
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

            $eventTnc = TermAndCon::where('id', $validatedData['tnc_id'])
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
                ->whereNull('event_id')
                ->exists();

            if (!$hasAcceptedTnc) {
                return $this->sendError(
                    'You must agree to the specified event terms and conditions to create an event.',
                    [],
                    403
                );
            }

            $organizer = $user->eventOrganizer;

            if (!$organizer) {
                $organizer = $user->eventOrganizer()->firstOrCreate(
                    ['eo_owner_id' => $user->id], // Kunci untuk mencari
                    [ // Data yang akan diisi jika tidak ditemukan
                        'name' => $user->name . ' Organizer',
                        'organizer_type' => 'individual',
                        'phone_no_eo' => '0000', // Placeholder
                        'address_eo' => 'Alamat belum diisi', // Placeholder
                    ]
                );
            }

            $event = DB::transaction(function () use ($validatedData, $user, $eventTnc, $organizer, $request) {
                $posterPath = null;
                if ($request->hasFile('poster')) {
                    $posterPath = $this->storeFile($request->file('poster'), 'event_posters');
                }

                $createdEvent = Event::create([
                    'eo_id' => $organizer->id,
                    'name' => $validatedData['name'],
                    'poster' => $posterPath,
                    'description' => $validatedData['description'],
                    'start_date' => $validatedData['start_date'],
                    'start_time' => $validatedData['start_time'],
                    'end_date' => $validatedData['end_date'],
                    'end_time' => $validatedData['end_time'],
                    'location' => $validatedData['location'],
                    'status' => 'draft',
                    'contact_phone' => $validatedData['contact_phone'],
                    'tnc_id' => $eventTnc->id,
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

                $user->tncStatuses()
                    ->where('tnc_id', $eventTnc->id)
                    ->whereNull('event_id')
                    ->update(['event_id' => $createdEvent->id]);

                return $createdEvent->load(['facilities', 'tickets']);
            });

            return $this->sendResponse(
                new EventResource($event),
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

    public function update(Request $request, Event $event)
    {
        // dd($event->is_published);
        if (Auth::id() !== $event->eventOrganizer->eo_owner_id) {
            return $this->sendError('You are not authorized to update this event.', [], 403);
        }

        if ($event->status !== EventStatusEnum::DRAFT) {
            return response()->json(['message' => 'Only draft events can be updated'], 403);
        }
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'start_date' => 'sometimes|required|date',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'end_time' => 'sometimes|required|date_format:H:i',
            'location' => 'sometimes|required|string|max:255',
            'contact_phone' => 'sometimes|required|string|max:20',
            'is_public' => 'sometimes|boolean', // <-- TAMBAHKAN INI
            'facilities' => 'nullable|array|exists:facilities,id',
            'tickets' => 'nullable|array',
            'tickets.*.id' => 'sometimes|required|exists:tickets,id',
            'tickets.*.name' => 'required_with:tickets|string|max:255',
            'tickets.*.price' => 'required_with:tickets|numeric|min:0',
            'tickets.*.stock' => 'required_with:tickets|integer|min:0',
            'tickets.*.limit' => 'required_with:tickets|integer|min:1',
            'tickets.*.start_date' => 'required_with:tickets|date', // Hapus before_or_equal untuk fleksibilitas
            'tickets.*.end_date' => 'required_with:tickets|date',   // Hapus before_or_equal untuk fleksibilitas
            'tickets.*.ticket_type_id' => 'required_with:tickets|exists:ticket_types,id',
        ]);

        $isOnlyChangingVisibility = count($validated) === 1 && isset($validated['is_public']);

        try {
            if ($isOnlyChangingVisibility) {
                if ($event->status !== EventStatusEnum::ACTIVE) {
                    return $this->sendError('Only active (published) events can be made public or private.', [], 403);
                }

                $event->update(['is_public' => $validated['is_public']]);
                $newStatus = $event->is_public ? 'Public' : 'Private';
                return $this->sendResponse($event, 'Event visibility has been successfully changed to ' . $newStatus);
            }

            if ($event->status !== EventStatusEnum::DRAFT) {
                return $this->sendError('Only draft events can be fully updated. To change visibility, please send the \'is_public\' field only.', [], 403);
            }

            $updatedEvent = DB::transaction(function () use ($validated, $event, $request) {
                $eventData = Arr::except($validated, ['facilities', 'tickets', 'poster']);

                if ($request->hasFile('poster')) {
                    if ($event->poster)
                        $this->deleteFile($event->poster);
                    $eventData['poster'] = $this->storeFile($request->file('poster'), 'event_posters');
                } elseif (array_key_exists('poster', $validated) && is_null($validated['poster'])) {
                    if ($event->poster) {
                        $this->deleteFile($event->poster);
                        $eventData['poster'] = null;
                    }
                }

                if (!empty($eventData)) {
                    $event->update($eventData);
                }

                if (array_key_exists('facilities', $validated)) {
                    $event->facilities()->sync($validated['facilities'] ?? []);
                }

                if (array_key_exists('tickets', $validated)) {
                    $ticketIdsToKeep = [];
                    foreach ($validated['tickets'] ?? [] as $ticketData) {
                        $ticketId = $ticketData['id'] ?? null;
                        if ($ticketId) {
                            // Update
                            $ticket = $event->tickets()->find($ticketId);
                            if ($ticket) {
                                $ticket->update(Arr::except($ticketData, ['id']));
                                $ticketIdsToKeep[] = $ticket->id;
                            }
                        } else {
                            // Create
                            $newTicket = $event->tickets()->create($ticketData);
                            $ticketIdsToKeep[] = $newTicket->id;
                        }
                    }
                    // Delete tickets yang tidak ada di request
                    $event->tickets()->whereNotIn('id', $ticketIdsToKeep)->delete();
                }

                return $event->fresh(['facilities', 'tickets']);
            });

            return $this->sendResponse(new EventResource($updatedEvent), 'Event updated successfully');

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

            if (Auth::id() !== $event->eventOrganizer->eo_owner_id) {
                return $this->sendError('You are not authorized to delete this event.', [], 403);
            }

            DB::transaction(function () use ($event) {
                if ($event->poster) {
                    $this->deleteFile($event->poster);
                }
                $event->facilities()->detach();
                $event->tickets()->delete();
                $event->delete();
            });

            return response()->json(['message' => 'Event deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong while deleting the event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function publish(Request $request, Event $event)
    {
        // dd($event);
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $organizer = $event->eventOrganizer;

            if ($user->id !== $organizer->eo_owner_id) {
                DB::rollBack();
                return $this->sendError('You are not authorized to publish this event.', [], 403);
            }

            if ($event->is_published) {
                DB::rollBack();
                return $this->sendError('Event is already published.', [], 400);
            }

            if ($organizer->phone_no_eo === '0000' || $organizer->address_eo === 'Alamat belum diisi') {
                DB::rollBack();
                return $this->sendError(
                    'Please complete your Event Organizer profile (address, phone number, etc.) before publishing.',
                    ['action_required' => 'UPDATE_EO_PROFILE'],
                    422
                );
            }

            if (!$organizer->hasUploadedRequiredDocuments()) {
                DB::rollBack();
                return $this->sendError(
                    'Please upload all required documents for your profile (e.g., KTP for Individual) before publishing.',
                    ['action_required' => 'UPLOAD_DOCUMENTS'],
                    422
                );
            }

            $event->update([
                'is_published' => true,
                'status' => EventStatusEnum::ACTIVE
            ]);
            DB::commit();

            return $this->sendResponse($event, 'Event published successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to publish event: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to publish event'], 500);
        }
    }

    public function deactivate(Event $event)
    {
        // dd($event->status);
        if (Auth::id() !== $event->eventOrganizer->eo_owner_id) {
            return $this->sendError('You are not authorized to deactivate this event.', [], 403);
        }

        if ($event->status !== EventStatusEnum::ACTIVE) {
            return $this->sendError('Only active events can be deactivated.', ['current_status' => $event->status], 400);
        }

        try {
            // Ubah status menjadi 'inactive'
            $event->update([
                'status' => EventStatusEnum::INACTIVE->value,
                'is_published' => false,
                'is_public' => false,
            ]);

            return $this->sendResponse(
                new EventResource($event),
                'Event has been successfully deactivated.'
            );
        } catch (\Exception $e) {
            Log::error('Failed to deactivate event ID ' . $event->id . ': ' . $e->getMessage());
            return $this->sendError('An unexpected error occurred while deactivating the event.', [], 500);
        }
    }

    public function archive(Event $event)
    {
        if (Auth::id() !== $event->eventOrganizer->eo_owner_id) {
            return $this->sendError('You are not authorized to archive this event.', [], 403);
        }

        if ($event->status !== EventStatusEnum::INACTIVE) {
            return $this->sendError('Only inactive events can be archived.', ['current_status' => $event->status], 400);
        }

        try {
            $event->update(['status' => EventStatusEnum::ARCHIVE->value]);

            return $this->sendResponse(
                new EventResource($event),
                'Event has been successfully archived.'
            );
        } catch (\Exception $e) {
            Log::error('Failed to archive event ID ' . $event->id . ': ' . $e->getMessage());
            return $this->sendError('An unexpected error occurred while archiving the event.', [], 500);
        }
    }
}
