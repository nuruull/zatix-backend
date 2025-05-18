<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Models\EventOrganizer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventOrganizerController extends BaseController
{
    public function index(Request $request)
    {
        $organizers = EventOrganizer::with('eo_owner')->latest()->get();

        return $this->sendResponse(
            $organizers,
            'List of Event Organizers'
        );
    }

    public function show($id)
    {
        try {
            $organizer = EventOrganizer::with('eo_owner')->findOrFail($id);

            return $this->sendResponse(
                $organizer,
                'Event Organizer found'
            );
        } catch (ModelNotFoundException $e) {
            return $this->sendError(
                'Event Organizer not found'
            );
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'eo_owner_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'logo' => 'nullable|string',
            'description' => 'nullable|string',
            'email_eo' => 'nullable|email',
            'phone_no_eo' => 'nullable|string|max:20',
            'address_eo' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        $organizer = EventOrganizer::create($validator->validated());

        return $this->sendResponse(
            $organizer,
            'Event Organizer created successfully'
        );
    }

    public function update(Request $request, $id)
    {
        try {
            $organizer = EventOrganizer::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'logo' => 'nullable|string',
                'description' => 'nullable|string',
                'email_eo' => 'nullable|email',
                'phone_no_eo' => 'nullable|string|max:20',
                'address_eo' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError(
                    'Validation failed',
                    $validator->errors(),
                    422
                );
            }

            $organizer->update($validator->validated());

            return $this->sendResponse(
                $organizer,
                'Event Organizer updated successfully'
            );

        } catch (ModelNotFoundException $e) {
            return $this->sendError(
                'Event Organizer not found',
                [],
                404
            );
        }
    }

    public function destroy($id)
    {
        try {
            $organizer = EventOrganizer::findOrFail($id);
            $organizer->delete();

            return $this->sendResponse(
                null,
                'Event Organizer deleted successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->sendError(
                'Event Organizer not found',
                [],
                404
            );
        }
    }

}
