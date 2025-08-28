<?php

namespace App\Http\Controllers\API\Events;

use Throwable;
use App\Models\User;
use App\Models\Event;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Notifications\WelcomeAndSetPasswordNotification;

class StaffController extends BaseController
{
    public function index(Event $event)
    {
        try {
            $currentUser = Auth::user();

            if ($currentUser->hasRole('eo-owner')) {
                $eventOrganizer = $currentUser->eventOrganizer;
                if (!$eventOrganizer || $event->eo_id !== $eventOrganizer->id) {
                    return $this->sendError("Unauthorized. This event does not belong to your organization.", [], 403);
                }
            } elseif ($currentUser->hasRole('event-pic')) {
                if (!$currentUser->events()->where('events.id', $event->id)->exists()) {
                    return $this->sendError("Unauthorized. You are not assigned to this event.", [], 403);
                }
            } else {
                return $this->sendError("Unauthorized.", [], 403);
            }

            $staffs = $event->staff()->with('roles')->paginate(15);

            return $this->sendResponse($staffs, 'Staff for event retrieved successfully.');
        } catch (Throwable $e) {
            Log::error('Failed to retrieve staff: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('Failed to retrieve staff.', ['error' => 'An unexpected server error occurred.'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $currentUser = Auth::user();

            $eventOrganizer = null;
            $event = null;

            $initialValidator = Validator::make($request->all(), [
                'event_id' => 'required|integer|exists:events,id',
            ]);
            if ($initialValidator->fails()) {
                throw new HttpResponseException($this->sendError('Validation failed', $initialValidator->errors(), 422));
            }
            $eventId = $request->input('event_id');
            $event = Event::find($eventId);

            if ($currentUser->hasRole('eo-owner')) {
                $eventOrganizer = $currentUser->eventOrganizer;
                if (!$eventOrganizer || $event->eo_id !== $eventOrganizer->id) {
                    DB::rollBack();
                    return $this->sendError("Unauthorized. Event does not belong to your organization.", [], 403);
                }
            } elseif ($currentUser->hasRole('event-pic')) {
                if (!$currentUser->events()->where('events.id', $eventId)->exists()) {
                    DB::rollBack();
                    return $this->sendError("Unauthorized. You are not assigned to manage this event.", [], 403);
                }
                $eventOrganizer = $event->eventOrganizer;
            }

            if (!$eventOrganizer) {
                DB::rollBack();
                return $this->sendError("Could not determine the Event Organizer profile for this user.", [], 403);
            }

            $creatableRoles = [];
            if ($currentUser->hasRole('eo-owner')) {
                $creatableRoles = ['event-pic', 'finance', 'crew', 'cashier'];
            } elseif ($currentUser->hasRole('event-pic')) {
                $creatableRoles = ['finance', 'crew', 'cashier'];
            } else {
                DB::rollback();
                return $this->sendError("You do not have permission to create staff.", [], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255',
                'role' => ['required', 'string', Rule::in($creatableRoles)],
                'event_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                throw new HttpResponseException(
                    $this->sendError('Validation failed', $validator->errors(), 422)
                );
            }

            $validated = $validator->validated();
            $roleToCreate = $validated['role'];

            if ($roleToCreate === 'event-pic') {
                $picExists = User::role('event-pic')
                    ->whereHas('events', function ($query) use ($eventId) {
                        $query->where('events.id', $eventId);
                    })
                    ->exists();
                if ($picExists) {
                    DB::rollback();
                    return $this->sendError(
                        "An Event PIC already exists for this event. Only one is allowed.",
                        ['status' => 'PIC_ALREADY_EXISTS'],
                        409 // 409 Conflict adalah status yang tepat untuk ini
                    );
                }
            }

            $staffUser = User::where('email', $validated['email'])->first();
            $isNewUser = false;

            if ($staffUser) { // Jika user dengan email ini sudah ada
                if ($staffUser->events()->where('events.id', $event->id)->exists()) {
                    DB::rollBack();
                    return $this->sendError('This staff member is already assigned to this event.', [], 409);
                }
            } else { // Jika user belum ada, buat akun baru
                $isNewUser = true;
                $staffUser = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make(Str::random(40)),
                    'email_verified_at' => now(),
                    'created_by' => $currentUser->id,
                ]);
            }

            $staffUser->assignRole($roleToCreate);
            $eventOrganizer->members()->attach($staffUser->id, ['event_id' => $eventId]);
            $staffUser->events()->attach($eventId);

            if ($isNewUser) {
                $token = Password::broker()->createToken($staffUser);
                $staffUser->notify(new WelcomeAndSetPasswordNotification($token));
            }

            DB::commit();

            $responseData = [
                'name' => $staffUser->name,
                'email' => $staffUser->email,
                'role' => $roleToCreate,
                'assigned_to_event' => $event->name,
                'is_new_user' => $isNewUser,
            ];

            return $this->sendResponse(
                $responseData,
                'Staff member created and assigned to event successfully.',
                201
            );
        } catch (HttpResponseException $e) {
            DB::rollBack();
            throw $e;
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create staff member: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return $this->sendError('Failed to create staff member.', ['error' => 'An unexpected server error occurred.'], 500);
        }
    }

    public function update(Request $request, Event $event, User $staff)
    {
        try {
            DB::beginTransaction();
            $currentUser = Auth::user();
            $eventOrganizer = null;

            if ($currentUser->hasRole('eo-owner')) {
                $eventOrganizer = $currentUser->eventOrganizer;
                if (!$eventOrganizer || $event->eo_id !== $eventOrganizer->id) {
                    return $this->sendError('Unauthorized. This event does not belong to your organization.', [], 403);
                }
            } elseif ($currentUser->hasRole('event-pic')) {
                if (!$currentUser->events()->where('events.id', $event->id)->exists()) {
                    return $this->sendError("Unauthorized. You are not assigned to this event.", [], 403);
                }
                $eventOrganizer = $event->eventOrganizer;
            }

            if (!$eventOrganizer) {
                return $this->sendError('Unauthorized. Could not determine an Event Organizer profile.', [], 403);
            }

            if (!$staff->events()->where('events.id', $event->id)->exists()) {
                return $this->sendError('Unauthorized. Staff not assigned to this event.', [], 403);
            }
            if ($currentUser->id === $staff->id) {
                return $this->sendError('You cannot edit your own role using this feature.', [], 403);
            }

            $editableRoles = [];
            if ($currentUser->hasRole('eo-owner')) {
                $editableRoles = ['event-pic', 'finance', 'crew', 'cashier'];
            } elseif ($currentUser->hasRole('event-pic')) {
                if ($staff->created_by !== $currentUser->id) {
                    return $this->sendError('Unauthorized. You can only edit staff that you created.', [], 403);
                }
                $editableRoles = ['finance', 'crew', 'cashier'];
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'role' => ['sometimes', 'required', 'string', Rule::in($editableRoles)],
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors(), 422);
            }
            $validated = $validator->validated();

            if (isset($validated['role']) && $validated['role'] === 'event-pic') {
                // Cek hanya jika role staff yang diedit BUKAN PIC sebelumnya
                if (!$staff->hasRole('event-pic')) {
                    $picExists = User::role('event-pic')->whereHas('events', function ($q) use ($event) {
                        $q->where('events.id', $event->id);
                    })->exists();
                    if ($picExists) {
                        DB::rollBack();
                        return $this->sendError('An Event PIC already exists for this event.', [], 409); // 409 Conflict
                    }
                }
            }

            if (isset($validated['name'])) {
                $staff->update(['name' => $validated['name']]);
            }

            if (isset($validated['role'])) {
                $staff->syncRoles([$validated['role']]);
            }

            DB::commit();

            return $this->sendResponse($staff->fresh()->load('roles'), 'Staff updated successfully.');

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update staff: ' . $e->getMessage(), [
                'event_id' => $event->id,
                'staff_id' => $staff->id,
                'exception' => $e
            ]);
            return $this->sendError('Failed to update staff.', ['error' => 'An unexpected server error occurred.'], 500);
        }
    }

    public function destroy(Event $event, User $staff)
    {
        try {
            $currentUser = Auth::user();
            $eventOrganizer = $currentUser->eventOrganizer;

            if (!$eventOrganizer || $event->eo_id !== $eventOrganizer->id) {
                return $this->sendError('Unauthorized. You do not have access to this event.', [], 403);
            }

            if (!$staff->events()->where('events.id', $event->id)->exists()) {
                return $this->sendError('Staff member is not assigned to this specific event.', [], 404);
            }

            if ($currentUser->id === $staff->id) {
                return $this->sendError('You cannot remove yourself using this feature.', [], 403);
            }

            DB::transaction(function () use ($staff, $event) {

                DB::table('event_organizer_users')
                    ->where('user_id', $staff->id)
                    ->where('event_id', $event->id)
                    ->delete();

                $staff->events()->detach($event->id);

                if ($staff->events()->count() === 0) {
                    $staff->syncRoles([]);
                    Log::info("All staff roles removed from user {$staff->id} as they have no remaining event assignments.");
                }
            });

            return $this->sendResponse([], 'Staff successfully unassigned from the event.');

        } catch (Throwable $e) {
            Log::error('Failed to unassign staff: ' . $e->getMessage(), [
                'event_id' => $event->id,
                'staff_id' => $staff->id,
                'exception' => $e
            ]);
            return $this->sendError('Failed to unassign staff.', ['error' => 'An unexpected server error occurred.'], 500);
        }
    }

    public function getEventsForSelection()
    {
        try {
            $eventOrganizer = Auth::user()->eventOrganizer;

            if (!$eventOrganizer) {
                return $this->sendError("Event Organizer profile not found.", [], 404);
            }

            $events = $eventOrganizer->events()
                ->select('id', 'name')
                ->where('status', 'active')
                ->where('end_date', '>=', now()->toDateString())
                ->orderBy('start_date', 'asc')
                ->get();

            return $this->sendResponse($events, 'Events for selection retrieved successfully.');

        } catch (Throwable $e) {
            Log::error('Failed to retrieve events for selection: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve events.', [], 500);
        }
    }
}
