<?php

namespace App\Http\Requests\CustomField;

use App\Models\CustomFieldDefinition;
use App\Models\ProductCategory;
use App\Services\CustomFieldEntityRegistry;
use App\Services\CustomFieldService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomFieldDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CustomFieldDefinition::class);
    }

    public function rules(): array
    {
        $entityType = (string) $this->route('entityType');

        return [
            'name' => [
                'required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/',
                // Uniqueness is per group, not global: the same name is legitimately
                // reusable in a category group and in the model group.
                Rule::unique('custom_field_definitions', 'name')
                    ->where(fn ($query) => $query->where('group_id', $this->groupIdFor($entityType))),
            ],
            'label' => 'required|string|max:100',
            'type' => ['required', Rule::in(CustomFieldDefinition::TYPES)],
            'options' => 'required_if:type,select,multi_select|nullable|array|min:1',
            'options.*' => 'string|max:100',
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
            'sort_order' => 'integer|min:0',
        ];
    }

    /**
     * The group is resolved from the route's entity type, never accepted from the
     * payload, so a client cannot write a definition into another model's group.
     */
    private function groupIdFor(string $entityType): ?int
    {
        $registry = app(CustomFieldEntityRegistry::class);

        if (! $registry->has($entityType)) {
            return null;
        }

        $service = app(CustomFieldService::class);
        $category = $this->route('category');

        if ($category instanceof ProductCategory) {
            return $service->categoryGroup($entityType, $category->id, $this->user()?->getKey())->id;
        }

        return $service->entityGroup($entityType, $this->user()?->getKey())->id;
    }
}
