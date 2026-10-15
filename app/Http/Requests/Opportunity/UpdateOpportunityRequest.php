<?php

namespace App\Http\Requests\Opportunity;

use App\Http\Requests\CustomField\ValidatesCustomFields;
use App\Models\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpportunityRequest extends FormRequest
{
    use ValidatesCustomFields;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('opportunity'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in(Opportunity::TYPES)],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'custom_fields' => ['nullable', 'array'],
            ...$this->customFieldRules('opportunity', true),
        ];
    }

    protected function defaultCustomFieldEntityType(): ?string
    {
        return 'opportunity';
    }
}
