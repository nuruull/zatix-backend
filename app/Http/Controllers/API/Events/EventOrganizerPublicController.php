<?php

namespace App\Http\Controllers\API\Events;

use Throwable;
use Illuminate\Http\Request;
use App\Models\EventOrganizer;
use App\Http\Resources\EventResource;
use App\Http\Controllers\BaseController;

class EventOrganizerPublicController extends BaseController
{
    public function getTopOrganizers()
    {
        try {
            $topOrganizers = EventOrganizer::query()
                ->withCount('tickets')
                ->orderBy('tickets_count', 'desc')
                ->take(5)
                ->get();

            return $this->sendResponse($topOrganizers, 'Top event organizers retrieved successfully.');
        } catch (Throwable $e) {
            return $this->sendError('Failed to retrieve top organizers.', ['error' => $e->getMessage()], 500);
        }
    }

    public function showEventsByOrganizer(EventOrganizer $organizer)
    {
        try {
            $events = $organizer->events()
                ->where('is_published', true)
                ->where('is_public', true)
                ->with(['format', 'topics'])
                ->latest()
                ->paginate(10);

            return EventResource::collection($events);

        } catch (Throwable $th) {
            return $this->sendError('Failed to retrieve organizer events.', ['error' => $th->getMessage()], 500);
        }
    }
}
