<?php

namespace App\Http\Requests\OpportunityActivity;

use App\Models\OpportunityActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteOpportunityActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('opportunity'));
    }

    public function rules(): array
    {
        return [
            'outcome' => ['nullable', 'string', Rule::in(OpportunityActivity::OUTCOMES)],
        ];
    }
}
