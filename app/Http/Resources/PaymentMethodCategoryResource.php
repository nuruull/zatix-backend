<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Http\Resources\PaymentMethodResource;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return[
            'name' => $this->name,
            'payment_methods' => PaymentMethodResource::collection($this->whenLoaded('paymentMethods')),
        ];
    }
}
