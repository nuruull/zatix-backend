<?php

namespace App\Http\Controllers\API\Events;

use DB;
use App\Models\Event;
use App\Models\Rundown;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
class RundownController extends BaseController
{
    public function __construct()
    {
        $this->authorizeResource(Rundown::class, 'rundown');
    }

    public function index(Event $event)
    {
        $rundowns = $event->rundowns()->orderBy('order', 'asc')->get();
        return $this->sendResponse($rundowns, 'Rundowns retrieved successfully.');
    }

    public function store(Request $request, Event $event)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_datetime' => 'required|date_format:Y-m-d H:i:s',
            'end_datetime' => 'required|date_format:Y-m-d H:i:s|after_or_equal:start_datetime',
            'order' => 'nullable|integer',
        ]);

        $rundown = $event->rundowns()->create($validated);
        return $this->sendResponse($rundown, 'Rundown created successfully.', 201);
    }

    public function show(Rundown $rundown)
    {
        return $this->sendResponse($rundown, 'Rundown detail retrieved successfully.');
    }

    public function update(Request $request, Rundown $rundown)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'start_datetime' => 'sometimes|required|date_format:Y-m-d H:i:s',
            'end_datetime' => 'sometimes|required|date_format:Y-m-d H:i:s|after_or_equal:start_datetime',
            'order' => 'nullable|integer',
        ]);

        $rundown->update($validated);
        return $this->sendResponse($rundown->fresh(), 'Rundown updated successfully.');
    }

    public function destroy(Rundown $rundown)
    {
        $rundown->delete();
        return $this->sendResponse([], 'Rundown deleted successfully.');
    }
}
