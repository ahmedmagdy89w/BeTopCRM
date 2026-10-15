<?php

namespace App\Http\Requests\Opportunity;

use App\Models\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpportunityStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('opportunity'));
    }

    public function rules(): array
    {
        return [
            'stage' => ['required', 'string', Rule::in(Opportunity::STAGES)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Opportunity $opportunity */
            $opportunity = $this->route('opportunity');
            if ($opportunity && ! $opportunity->canTransitionTo($this->input('stage'))) {
                $validator->errors()->add(
                    'stage',
                    "Cannot transition from [{$opportunity->stage}] to [{$this->input('stage')}]."
                );
            }
        });
    }
}
