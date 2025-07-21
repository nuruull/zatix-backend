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
            'description' => $this->description,

            // PERBAIKAN: Tambahkan pengecekan NULL sebelum memanggil format()
            'start_datetime' => $this->start_date ? $this->start_date->format('Y-m-d') . 'T' . $this->start_time : null,
            'end_datetime' => $this->end_date ? $this->end_date->format('Y-m-d') . 'T' . $this->end_time : null,

            'location' => $this->location,
            'status' => $this->status,
            'is_published' => (bool) $this->is_published,
            'is_public' => (bool) $this->is_public,
            'poster_url' => $this->when($this->poster, Storage::url($this->poster)),
            'organizer' => new EventOrganizerResource($this->whenLoaded('eventOrganizer')),
        ];
    }
}
