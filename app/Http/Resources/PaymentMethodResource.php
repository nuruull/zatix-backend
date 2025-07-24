<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->bank->name,
            'code' => $this->bank->code,
            'type' => $this->bank->type,
            'image' => url($this->bank->main_image), 
        ];
    }
}
