<?php

namespace App\Http\Resources\Invoice;

use App\Http\Resources\CompanySettings\CompanySettingResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function __construct(mixed $resource, private array $document)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'tax_percent' => $this->tax_percent,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'total' => $this->total,
            'issued_at' => $this->issued_at?->toDateString(),
            'opportunity_id' => $this->opportunity_id,
            'customer_id' => $this->customer_id,
            'quotation_id' => $this->quotation_id,
            'quotation_number' => $this->whenLoaded('quotation', fn () => $this->quotation?->quotation_number),
            'company' => new CompanySettingResource(array_diff_key($this->document['company'], ['logo_file_path' => true])),
            'customer' => $this->document['buyer'],
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
