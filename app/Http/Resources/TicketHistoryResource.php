<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ticket_code' => $this->ticket_code,
            'attendee_name' => $this->attendee_name,
            'ticket_type' => $this->whenLoaded('ticket', $this->ticket->name),
            'checked_in_at' => $this->checked_in_at->format('d M Y, H:i:s'),
            'scanned_by' => $this->whenLoaded('checkedInBy', $this->checkedInBy->name),
        ];
    }
}
