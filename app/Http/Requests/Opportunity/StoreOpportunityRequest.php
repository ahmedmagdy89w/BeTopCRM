<?php

namespace App\Http\Requests\Opportunity;

use App\Http\Requests\CustomField\ValidatesCustomFields;
use App\Models\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpportunityRequest extends FormRequest
{
    use ValidatesCustomFields;

    public function authorize(): bool
    {
        return $this->user()->can('create', Opportunity::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in(Opportunity::TYPES)],
            'stage' => ['sometimes', 'string', Rule::in(Opportunity::STAGES)],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'custom_fields' => ['nullable', 'array'],
            ...$this->customFieldRules('opportunity'),
        ];
    }

    protected function defaultCustomFieldEntityType(): ?string
    {
        return 'opportunity';
    }
}
