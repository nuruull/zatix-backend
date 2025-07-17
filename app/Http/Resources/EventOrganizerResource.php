<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class EventOrganizerResource extends JsonResource
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
            'type' => $this->organizer_type ? $this->organizer_type->value : null,
            'logo_url' => $this->when($this->logo, Storage::url($this->logo)),
            'description' => $this->description,
            'is_verified' => $this->is_verified,
        ];
    }
}
