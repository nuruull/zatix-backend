<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialTransactionResource extends JsonResource
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
            'type' => $this->type,
            'category' => $this->category,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'transaction_date' => $this->transaction_date->format('Y-m-d'),
            'proof_trans_url' => $this->when($this->proof_trans_url, Storage::url($this->proof_trans_url)),
            'recorded_by' => $this->whenLoaded('recorder', function () {
                return [
                    'id' => $this->recorder->id,
                    'name' => $this->recorder->name,
                ];
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
