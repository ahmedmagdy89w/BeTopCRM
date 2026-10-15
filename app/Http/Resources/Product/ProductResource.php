<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'unit_price' => $this->whenPivotLoaded('opportunity_products', fn () => $this->pivot->unit_price ?? $this->unit_price, $this->unit_price),
            'currency' => $this->currency,
            'category_id' => $this->category_id,
            'category' => $this->when($this->relationLoaded('category') && $this->category, fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'tax_id' => $this->tax_id,
            'tax' => $this->when($this->relationLoaded('tax') && $this->tax, fn () => [
                'id' => $this->tax->id,
                'name' => $this->tax->name,
                'percent' => $this->tax->percent,
            ]),
            'is_active' => $this->is_active,
            'custom_fields' => $this->custom_fields,
            'category_custom_fields' => $this->category_custom_fields,
            'created_by' => $this->when($this->relationLoaded('creator') && $this->creator, fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'quantity' => $this->whenPivotLoaded('opportunity_products', fn () => $this->pivot->quantity),
            'discount_percent' => $this->whenPivotLoaded('opportunity_products', fn () => $this->pivot->discount_percent),
            'discount_amount' => $this->whenPivotLoaded('opportunity_products', fn () => $this->pivot->discount_amount ?? '0.00'),
            'notes' => $this->whenPivotLoaded('opportunity_products', fn () => $this->pivot->notes),
            'created_at' => $this->created_at,
        ];
    }
}
