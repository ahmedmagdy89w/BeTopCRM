<?php

namespace App\Http\Resources\CustomField;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin array{entity_type: string, label: string, supports_categories: bool} */
class CustomFieldEntityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'entity_type' => $this['entity_type'],
            'label' => $this['label'],
            'supports_categories' => $this['supports_categories'],
        ];
    }
}
