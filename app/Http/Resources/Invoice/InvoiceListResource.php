<?php

namespace App\Http\Resources\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'document_type' => 'invoice',
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'currency' => $this->currency,
            'total' => $this->total,
            'issued_at' => $this->issued_at?->toDateString(),
            'customer_id' => $this->customer_id,
            'opportunity_id' => $this->opportunity_id,
            'quotation_id' => $this->quotation_id,
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
