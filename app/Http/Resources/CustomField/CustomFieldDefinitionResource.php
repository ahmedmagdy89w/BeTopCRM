<?php

namespace App\Http\Resources\CustomField;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomFieldDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'entity_type' => $this->whenLoaded('group', fn () => $this->group?->entity_type, $this->entityType()),
            'scope' => $this->whenLoaded('group', fn () => $this->group?->scope, $this->group?->scope),
            'category_id' => $this->whenLoaded('group', fn () => $this->group?->category_id, $this->group?->category_id),
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'options' => $this->options,
            'is_required' => $this->is_required,
            'is_filterable' => $this->is_filterable,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
        ];
    }
}
