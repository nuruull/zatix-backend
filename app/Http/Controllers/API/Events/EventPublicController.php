<?php

namespace App\Http\Controllers\API\Events;

use App\Models\Event;
use Illuminate\Http\Request;
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
        $event->load(['facilities', 'tickets', 'eventOrganizer']);
        return $this->sendResponse($event, 'Event retrieved successfully.');
    }
}
