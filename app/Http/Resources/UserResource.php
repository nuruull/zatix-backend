<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            // Ambil nama peran dari relasi yang sudah di-load
            'roles' => $this->whenLoaded('roles', $this->roles->pluck('name')),
            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}
