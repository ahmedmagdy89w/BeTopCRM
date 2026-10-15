<?php

namespace App\Http\Resources\Quotation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'document_type' => 'quotation',
            'id' => $this->id,
            'quotation_number' => $this->quotation_number,
            'status' => $this->status,
            'currency' => $this->currency,
            'total' => $this->total,
            'valid_until' => $this->valid_until?->toDateString(),
            'sent_at' => $this->sent_at,
            'customer_id' => $this->customer_id,
            'opportunity_id' => $this->opportunity_id,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'display_name' => $this->customer->display_name,
            ]),
            'opportunity' => $this->whenLoaded('opportunity', fn () => [
                'id' => $this->opportunity->id,
                'title' => $this->opportunity->title,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
