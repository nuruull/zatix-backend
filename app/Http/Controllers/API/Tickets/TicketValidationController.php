<?php

namespace App\Http\Controllers\API\Tickets;

use DB;
use Auth;
use App\Models\ETicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Enum\Status\OrderStatusEnum;
use App\Http\Controllers\BaseController;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TicketValidationController extends BaseController
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|exists:events,id',
        ]);

        try {
            $checkedInTickets = ETicket::query()
                ->whereNotNull('check_in_at')
                ->whereHas('ticket', function ($query) use ($validated) {
                    // PERBAIKAN: Menggunakan where() untuk filter, bukan when().
                    $query->where('event_id', $validated['event_id']);
                })
                ->with([
                    'user:id,name',
                    'ticket:id,name',
                    'checkedInBy:id,name'
                ])
                ->latest('check_in_at')
                ->paginate(25);

            return $this->sendResponse(
                $checkedInTickets,
                'Check-in history was retrieved successfully.'
            );
        } catch (\Exception $e) {
            Log::error("Failed to retrieve checked-in ticket history.", [
                'event_id' => $validated['event_id'],
                'error' => $e->getMessage()
            ]);
            return $this->sendError('An error occurred while retrieving the check-in history.', [], 500);
        }
    }

    public function validateTicket(Request $request)
    {
        $validated = $request->validate([
            'ticket_code' => 'required|string|exists:e_tickets,ticket_code',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $now = now();
                $crew = Auth::user();

                $eTicket = ETicket::with(['order', 'ticket.event', 'checkedInBy'])
                    ->where('ticket_code', $validated['ticket_code'])
                    ->firstOrFail();

                $crewEOs = DB::table('event_organizer_users')->where('user_id', $crew->id)->pluck('eo_id');
                $ticketEventEO = $eTicket->ticket->event->eo_id;

                if (!$crewEOs->contains($ticketEventEO)) {
                    return $this->sendError(
                        'Unauthorized. You are not a member of the Event Organizer of this event.',
                        ['status' => 'UNAUTHORIZED_CREW'],
                        403 // Forbidden
                    );
                }

                if (!$eTicket->ticket->event->is_published) {
                    return $this->sendError(
                        'Ticket not valid due to event inactivity.',
                        ['status' => 'EVENT_NOT_ACTIVE'],
                        422
                    );
                }

                if ($eTicket->checked_in_at) {
                    $errorDetails = [
                        'status' => 'ALREADY_CHECKED_IN',
                        'data' => [
                            'checked_in_at' => $eTicket->checked_in_at->format('d M Y, H:i:s'),
                            'checked_in_by' => $eTicket->checkedInBy->name ?? 'N/A',
                        ],
                    ];
                    return $this->sendError(
                        'Ticket already checked in.',
                        $errorDetails,
                        409
                    );
                }

                if ($eTicket->order->status->value !== OrderStatusEnum::PAID->value) {
                    $errorDetails = [
                        'status' => 'ORDER_NOT_PAID',
                        'data' => [
                            'order_status' => $eTicket->order->status->value
                        ]
                    ];
                    return $this->sendError(
                        'Tickets are not valid (Payment has not been paid in full or canceled).',
                        $errorDetails,
                        422
                    );
                }

                $eTicket->update([
                    'checked_in_at' => $now,
                    'checked_in_by' => $crew->id
                ]);

                Log::info("Ticket checked in successfully.", [
                    'ticket_code' => $eTicket->ticket_code,
                    'event_id' => $eTicket->ticket->event_id,
                    'crew_id' => $crew->id
                ]);

                $successData = [
                    'attendee_name' => $eTicket->attendee_name,
                    'ticket_type' => $eTicket->ticket->name,
                    'event_name' => $eTicket->ticket->event->name,
                    'checked_in_at' => $now->format('d M Y, H:i:s'),
                    'checked_in_by' => $crew->name,
                ];

                return $this->sendResponse(
                    $successData,
                    'Check-in was successful!',
                );
            });
        } catch (ModelNotFoundException $e) {
            return $this->sendError(
                'Ticket not found.',
                [],
                404
            );
        } catch (\Exception $e) {
            Log::error("Ticket validation failed unexpectedly.", [
                'ticket_code' => $validated['ticket_code'],
                'error' => $e->getMessage()
            ]);
            return $this->sendError(
                'An error occurred on the server.',
                [],
                500
            );
        }
    }
}
