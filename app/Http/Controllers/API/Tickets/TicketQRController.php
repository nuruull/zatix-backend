<?php

namespace App\Http\Controllers\API\Tickets;

use App\Http\Controllers\BaseController;
use App\Models\ETicket;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class TicketQRController extends BaseController
{
    public function show(Request $request, string $ticket_code)
    {
        try {
            $eTicket = ETicket::where('ticket_code', $ticket_code)->firstOrFail();
            $user = $request->user();

            if ($user->id !== $eTicket->user_id) {
                return $this->sendError('You are not authorized to view this ticket.', [], 403);
            }

            $builder = new Builder();

            $result = $builder->build(
                writer: new SvgWriter(),
                data: $eTicket->ticket_code,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 300,
                margin: 10
            );

            return response($result->getString())->header('Content-Type', $result->getMimeType());
        } catch (ModelNotFoundException $e) {
            return $this->sendError(
                'Ticket not found',
                [],
                404
            );
        }
    }
}
