<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index()
    {
        try {
            $tickets = Ticket::with(['event', 'ticketType'])->get();
            return response()->json($tickets);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch tickets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'event_id' => 'required|exists:events,id',
                'ticket_type_id' => 'nullable|exists:ticket_types,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|integer|min:0',
                'quota' => 'required|integer|min:1',
            ]);

            $ticket = Ticket::create($request->all());

            return response()->json($ticket, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create ticket', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $ticket = Ticket::findOrFail($id);

            $request->validate([
                'event_id' => 'sometimes|exists:events,id',
                'ticket_type_id' => 'nullable|exists:ticket_types,id',
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'price' => 'sometimes|integer|min:0',
                'quota' => 'sometimes|integer|min:1',
            ]);

            $ticket->update($request->all());

            return response()->json($ticket);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update ticket', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $ticket = Ticket::findOrFail($id);
            $ticket->delete();

            return response()->json(['message' => 'Ticket deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete ticket', 'error' => $e->getMessage()], 500);
        }
    }
}
