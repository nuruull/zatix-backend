<?php

namespace App\Http\Controllers\API\Events;

use DB;
use App\Models\Event;
use App\Models\Rundown;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\RundownResource;
use App\Http\Controllers\BaseController;

class RundownController extends BaseController
{
    public function __construct()
    {
        $this->authorizeResource(Rundown::class, 'rundown', [
            'except' => ['index', 'store'],
        ]);
    }

    public function index(Event $event)
    {
        $this->authorize('viewAny', [Rundown::class, $event]);

        $rundowns = $event->rundowns()->orderBy('order', 'asc')->get();
        return $this->sendResponse(RundownResource::collection($rundowns), 'Rundowns retrieved successfully.');
    }

    public function store(Request $request, Event $event)
    {
        $this->authorize('create', [Rundown::class, $event]);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_datetime' => 'required|date_format:Y-m-d H:i:s',
            'end_datetime' => 'required|date_format:Y-m-d H:i:s|after_or_equal:start_datetime',
            'order' => 'nullable|integer',
        ]);

        try {
            $rundown = $event->rundowns()->create($validated);
            $resource = new RundownResource($rundown);

            $rundown = DB::transaction(function () use ($event, $validated) {
                $newRundown = $event->rundowns()->create($validated);

                return $newRundown;
            });

            return $this->sendResponse($resource, 'Rundown created successfully.', 201);
        } catch (\Exception $e) {
            Log::error('Failed to create rundown for event ID: ' . $event->id, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('An unexpected error occurred while creating the rundown.', [], 500);
        }
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

        try {
            $rundown->update($validated);
            return $this->sendResponse($rundown->fresh(), 'Rundown updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update rundown ID: ' . $rundown->id, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('An unexpected error occurred while updating the rundown.', [], 500);
        }
    }

    public function destroy(Rundown $rundown)
    {
        try {
            $rundown->delete();
            return $this->sendResponse([], 'Rundown deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete rundown ID: ' . $rundown->id, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('An unexpected error occurred while deleting the rundown.', [], 500);
        }
    }
}
