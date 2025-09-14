<?php

namespace App\Http\Controllers\API\Events;

use Throwable;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\EventResource;
use App\Http\Controllers\BaseController;

class EventPublicController extends BaseController
{
    public function index(Request $request)
    {
        $events = Event::with(['facilities', 'tickets'])
            ->where('is_published', true)
            ->where('is_public', true)
            ->latest()
            ->paginate($request->input('per_page', 15));
        return $this->sendResponse($events, 'Events retrieved successfully.');
    }

    public function show(Event $event)
    {
        if ($event->is_published && $event->is_public) {

            $event->load(['facilities', 'tickets', 'eventOrganizer']);

            return $this->sendResponse($event, 'Event retrieved successfully.');
        }
        return $this->sendError('Event not found.', [], 404);
    }


    public function getPopularEvents()
    {
        try {
            $query = Event::query()
                ->where('is_published', true)
                ->where('start_date', '>=', now()->toDateString());

            $query->withCount('bookmarkedByUsers');

            $query->orderBy('bookmarked_by_users_count', 'desc');

            $popularEvents = $query->take(8)->get();

            if ($popularEvents->isEmpty() || $popularEvents->first()->bookmarked_by_users_count === 0) {
                Log::info('No bookmarked events found, falling back to latest events.');
                $popularEvents = Event::query()
                    ->where('is_published', true)
                    ->where('start_date', '>=', now()->toDateString())
                    ->latest()
                    ->take(8)
                    ->get();
            }

            // Anda bisa menggunakan EventResource di sini
            return $this->sendResponse(EventResource::collection($popularEvents), 'Popular events retrieved successfully.');

        } catch (Throwable $e) {
            return $this->sendError('Failed to retrieve popular events.', ['error' => $e->getMessage()], 500);
        }
    }
}
