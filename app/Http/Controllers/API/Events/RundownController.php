<?php

namespace App\Http\Controllers\API\Events;

use DB;
use App\Models\Event;
use App\Models\Rundown;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\BaseController;
use Illuminate\Validation\ValidationException;

class RundownController extends BaseController
{
    public function index(Request $request)
    {
        try {
            $validated = $request->validate(['event_id' => 'required|integer|exists:events,id']);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        }

        $event = Event::find($validated['event_id']);
        $user = Auth::guard('sanctum')->user();

        $canView = false;

        if ($event->is_rundown_published) {
            $canView = true;
        } elseif ($user) {
            $isOwner = $user->hasRole('eo-owner') && $event->eventOrganizer->eo_owner_id === $user->id;
            $isCrewOfEvent = $user->hasRole('crew') && DB::table('event_organizer_users')->where('user_id', $user->id)->where('eo_id', $event->eo_id)->exists();

            if ($user->hasRole('super-admin') || $isOwner || $isCrewOfEvent) {
                $canView = true;
            }
        }

        if ($canView) {
            $rundowns = $event->rundowns()->get();
            return $this->sendResponse($rundowns, 'The rundown was successfully taken.');
        }

        return $this->sendError('Unauthorized. You do not have access to this event rundown.', [], 403);
    }

    public function store(Request $request, Event $event)
    {
        if (!Auth::user()->hasRole('eo-owner') || $event->eventOrganizer->eo_owner_id !== Auth::id()) {
            return $this->sendError('Unauthorized. You are not the owner of this event.', [], 403);
        }

        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_datetime' => 'required|date_format:Y-m-d H:i:s',
                'end_datetime' => 'required|date_format:Y-m-d H:i:s|after_or_equal:start_datetime',
                'order' => 'nullable|integer',
            ]);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        }

        // Buat rundown baru melalui relasi
        $rundown = $event->rundowns()->create($validated);

        return $this->sendResponse($rundown, 'The rundown was successfully created.', 201);
    }


    public function show(Rundown $rundown)
    {
        $user = Auth::guard('sanctum')->user();
        $event = $rundown->event;

        $canView = false;

        if ($event->is_rundown_published) {
            $canView = true;
        } elseif ($user) {
            $isOwner = $user->hasRole('eo-owner') && $event->eventOrganizer->eo_owner_id === $user->id;
            $isCrewOfEvent = $user->hasRole('crew') && DB::table('event_organizer_users')->where('user_id', $user->id)->where('eo_id', $event->eo_id)->exists();

            if ($user->hasRole('super-admin') || $isOwner || $isCrewOfEvent) {
                $canView = true;
            }
        }

        if ($canView) {
            return $this->sendResponse($rundown, 'Rundown detail retrieved successfully');
        }

        return $this->sendError('Unauthorized. You do not have access to this rundown.', [], 403);
    }

    public function update(Request $request, Rundown $rundown)
    {
        if (!Auth::user()->hasRole('eo-owner') || $rundown->event->eventOrganizer->eo_owner_id !== Auth::id()) {
            return $this->sendError('Unauthorized. You are not the owner of this event.', [], 403);
        }

        try {
            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'start_datetime' => 'sometimes|required|date_format:Y-m-d H:i:s',
                'end_datetime' => 'sometimes|required|date_format:Y-m-d H:i:s|after_or_equal:start_datetime',
                'order' => 'nullable|integer',
            ]);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        }

        $rundown->update($validated);

        return $this->sendResponse($rundown->fresh(), 'Rundown updated successfully.');
    }

    public function destroy(Rundown $rundown)
    {
        if (!Auth::user()->hasRole('eo-owner') || $rundown->event->eventOrganizer->eo_owner_id !== Auth::id()) {
            return $this->sendError('Unauthorized. You are not the owner of this event.', [], 403);
        }

        $rundown->delete();

        return $this->sendResponse([], 'Rundown deleted successfully.');
    }
}
