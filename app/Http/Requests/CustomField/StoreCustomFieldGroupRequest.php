<?php

namespace App\Http\Requests\CustomField;

use App\Models\CustomFieldGroup;
use App\Services\CustomFieldEntityRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomFieldGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CustomFieldGroup::class);
    }

    public function rules(): array
    {
        $registry = app(CustomFieldEntityRegistry::class);
        $entityType = (string) $this->input('entity_type');
        $scope = (string) $this->input('scope');
        $categoryId = is_numeric($this->input('category_id')) ? (int) $this->input('category_id') : null;
        $scopeKey = $registry->has($entityType)
            ? CustomFieldEntityRegistry::scopeKey($scope, $categoryId)
            : '';
        $allowedScopes = $registry->hasCategoryScope($entityType)
            ? [CustomFieldEntityRegistry::SCOPE_ENTITY, CustomFieldEntityRegistry::SCOPE_CATEGORY]
            : [CustomFieldEntityRegistry::SCOPE_ENTITY];

        return [
            'entity_type' => [
                'required',
                'string',
                Rule::in($registry->types()),
                Rule::unique('custom_field_groups', 'entity_type')
                    ->where(fn ($query) => $query->where('scope_key', $scopeKey)),
            ],
            'scope' => ['required', Rule::in($allowedScopes)],
            'category_id' => [
                Rule::excludeIf($scope !== CustomFieldEntityRegistry::SCOPE_CATEGORY),
                'required',
                'integer',
                'exists:product_categories,id',
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
