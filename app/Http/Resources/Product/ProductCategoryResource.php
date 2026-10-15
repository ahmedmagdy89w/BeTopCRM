<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'parent_id'   => $this->parent_id,
            'description' => $this->description,
            'sort_order'  => $this->sort_order,
            'children'    => $this->when(
                $this->relationLoaded('children'),
                fn () => ProductCategoryResource::collection($this->children)
            ),
            'created_at'  => $this->created_at,
        ];
    }
}
