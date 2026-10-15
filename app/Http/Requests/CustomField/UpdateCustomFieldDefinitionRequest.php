<?php

namespace App\Http\Requests\CustomField;

use App\Models\CustomFieldDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomFieldDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('definition'));
    }

    public function rules(): array
    {
        /** @var CustomFieldDefinition $definition */
        $definition = $this->route('definition');

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('custom_field_definitions', 'name')
                    ->where(fn ($query) => $query->where('group_id', $definition->group_id))
                    ->ignore($definition->id),
            ],
            'label' => 'sometimes|string|max:100',
            'type' => ['sometimes', 'required', Rule::in(CustomFieldDefinition::TYPES)],
            'options' => 'sometimes|required_if:type,select,multi_select|nullable|array|min:1',
            'options.*' => 'string|max:100',
            'is_required' => 'sometimes|boolean',
            'is_filterable' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ];
    }
}
