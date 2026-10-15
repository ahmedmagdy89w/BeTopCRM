<?php

namespace App\Http\Resources\CustomField;

use App\Models\CustomFieldGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin array{group: CustomFieldGroup|null, definitions: Collection} */
class CustomFieldGroupWithDefinitionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'group' => $this['group'] === null
                ? null
                : new CustomFieldGroupResource($this['group']),
            'definitions' => CustomFieldDefinitionResource::collection($this['definitions']),
        ];
    }
}
