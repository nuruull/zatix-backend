<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RundownResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'start_time' => $this->start_datetime->format('H:i'), // Format waktu mulai
            'end_time' => $this->end_datetime->format('H:i'),     // Format waktu selesai
            'duration_minutes' => $this->end_datetime->diffInMinutes($this->start_datetime), // Data turunan
            'is_visible_to_public' => (bool) $this->is_public, // Casting ke boolean
            'order' => $this->order,
        ];
    }
}
