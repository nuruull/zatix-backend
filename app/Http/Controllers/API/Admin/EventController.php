<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Event;
use App\Http\Resources\EventResource;
use App\Http\Controllers\BaseController;

class EventController extends BaseController
{
    public function index()
    {
        $events = Event::query()
            ->where('is_published', true)
            ->with('eventOrganizer:id,name') // Eager load relasi
            ->latest()
            ->paginate(20);

        return EventResource::collection($events)
            ->additional([
                'success' => true,
                'message' => 'Published events retrieved successfully for admin.'
            ]);
    }
}
