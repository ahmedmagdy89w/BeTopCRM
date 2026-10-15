<?php

namespace App\Http\Resources\Quotation;

use App\Http\Resources\CompanySettings\CompanySettingResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
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
            'quotation_number' => $this->quotation_number,
            'status' => $this->status,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'tax_percent' => $this->tax_percent,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'total' => $this->total,
            'valid_until' => $this->valid_until?->toDateString(),
            'notes' => $this->notes,
            'terms' => $this->terms,
            'sent_at' => $this->sent_at,
            'opportunity_id' => $this->opportunity_id,
            'customer_id' => $this->customer_id,
            'company' => new CompanySettingResource(array_diff_key($this->document['company'], ['logo_file_path' => true])),
            'customer' => $this->document['buyer'],
            'items' => QuotationItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
        ];
    }
}
