<?php

namespace App\Http\Resources\Opportunity;

use App\Http\Resources\Tag\TagResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer' => $this->when($this->relationLoaded('customer') && $this->customer, fn () => [
                'id' => $this->customer->id,
                'display_name' => $this->customer->display_name,
                'type' => $this->customer->type,
                'is_lead' => (bool) $this->customer->is_lead,
            ]),
            'title' => $this->title,
            'type' => $this->type,
            'stage' => $this->stage,
            'is_open' => ! in_array($this->stage, ['won', 'lost']),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'probability' => $this->probability,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'closed_at' => $this->closed_at,
            'assigned_to' => $this->when($this->relationLoaded('assignee') && $this->assignee, fn () => [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
            'created_by' => $this->when($this->relationLoaded('creator'), fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'notes' => $this->notes,
            'custom_fields' => $this->custom_fields,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'products' => $this->whenLoaded('products', fn () => $this->products->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit_price' => $product->pivot->unit_price ?? $product->unit_price,
                'currency' => $product->currency,
                'quantity' => $product->pivot->quantity,
                'notes' => $product->pivot->notes,
            ])),
            'product_pivot' => $this->whenPivotLoaded('opportunity_products', fn () => [
                'quantity' => $this->pivot->quantity,
                'unit_price' => $this->pivot->unit_price,
                'notes' => $this->pivot->notes,
            ]),
            'activities_count' => $this->when(isset($this->activities_count), $this->activities_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
