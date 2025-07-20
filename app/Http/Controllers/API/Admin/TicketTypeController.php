<?php

namespace App\Http\Controllers\API\Admin;

use Throwable;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController;
use Illuminate\Validation\ValidationException;

class TicketTypeController extends BaseController
{
    public function index(Request $request)
    {
        try {
            $ticketTypes = TicketType::latest()->paginate($request->input('per_page', 15));
            return $this->sendResponse($ticketTypes, 'Ticket types retrieved successfully.');
        } catch (Throwable $th) {
            Log::error('Error retrieving ticket types: ' . $th->getMessage());
            return $this->sendError('Failed to retrieve ticket types.', [], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:ticket_types,name',
                'description' => 'nullable|string|max:1000',
            ]);

            $ticketType = TicketType::create($validated);

            return $this->sendResponse($ticketType, 'Ticket type created successfully.', 201);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Throwable $th) {
            Log::error('Error creating ticket type: ' . $th->getMessage());
            return $this->sendError('Failed to create ticket type.', [], 500);
        }
    }

    public function show(TicketType $ticketType)
    {
        return $this->sendResponse($ticketType, 'Ticket type retrieved successfully.');
    }

    public function update(Request $request, TicketType $ticketType)
    {
        try {
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('ticket_types')->ignore($ticketType->id),
                ],
                'description' => 'nullable|string|max:1000',
            ]);

            $ticketType->update($validated);

            return $this->sendResponse($ticketType, 'Ticket type updated successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Throwable $th) {
            Log::error('Error updating ticket type: ' . $th->getMessage());
            return $this->sendError('Failed to update ticket type.', [], 500);
        }
    }

    public function destroy(TicketType $ticketType)
    {
        try {
            if ($ticketType->tickets()->exists()) {
                return $this->sendError(
                    'This ticket type cannot be deleted because it is still in use by one or more tickets.',
                    [],
                    409 
                );
            }

            $ticketType->delete();

            return $this->sendResponse([], 'Ticket type deleted successfully.');
        } catch (Throwable $th) {
            Log::error('Error deleting ticket type: ' . $th->getMessage());
            return $this->sendError('Failed to delete ticket type.', [], 500);
        }
    }
}
