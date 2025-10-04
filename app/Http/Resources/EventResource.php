<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\EventOrganizerResource;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'format' => $this->format?->name,
            'topics' => $this->whenLoaded('topics', function () {
                return $this->topics->pluck('name');
            }),
            'description' => $this->description,
            'start_datetime' => $this->start_date ? $this->start_date->format('Y-m-d') . 'T' . $this->start_time?->format('H:i') : null,
            'end_datetime' => $this->end_date ? $this->end_date->format('Y-m-d') . 'T' . $this->end_time : null,
            'location' => $this->location,
            'max_tickets_per_transaction' => $this->max_tickets_per_transaction,
            'status' => $this->status,
            'is_published' => (bool) $this->is_published,
            'is_public' => (bool) $this->is_public,
            'poster_url' => $this->when($this->poster, Storage::url($this->poster)),
            'organizer' => new EventOrganizerResource($this->whenLoaded('eventOrganizer')),
        ];
    }
}
