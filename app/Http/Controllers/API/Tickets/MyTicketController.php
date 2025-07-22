<?php

namespace App\Http\Controllers\API\Tickets;

use App\Models\ETicket;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\BaseController;

class MyTicketController extends BaseController
{
    public function index(Request $request)
    {
        $eTickets = ETicket::query()
            ->where('user_id', Auth::id()) // <-- Filter utama: hanya tiket milik user ini
            ->with(['ticket:id,name', 'ticket.event:id,name,start_date']) // Eager load relasi
            ->latest() // Urutkan dari yang terbaru
            ->paginate(15, [ // Pilih kolom yang relevan saja untuk daftar
                'id',
                'ticket_code',
                'attendee_name',
                'ticket_id',
                'checked_in_at'
            ]);

        return $this->sendResponse($eTickets, 'My tickets retrieved successfully.');
    }

    public function show(ETicket $eTicket)
    {
        if ($eTicket->user_id !== Auth::id()) {
            return $this->sendError('Forbidden. You do not own this ticket.', [], 403);
        }

        $eTicket->load(['ticket.event', 'order', 'checkedInBy:id,name']);

        $eTicket->qr_code_url = route('e-tickets.show-qr', ['ticket_code' => $eTicket->ticket_code]);

        return $this->sendResponse($eTicket, 'Ticket details retrieved successfully.');
    }
}
