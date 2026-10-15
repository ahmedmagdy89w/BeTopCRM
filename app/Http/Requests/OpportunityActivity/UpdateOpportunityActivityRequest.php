<?php

namespace App\Http\Requests\OpportunityActivity;

use App\Models\OpportunityActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpportunityActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('opportunity'));
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(OpportunityActivity::TYPES)],
            'channel' => ['nullable', 'string', Rule::in(OpportunityActivity::CHANNELS)],
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'reminder_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $activity = $this->route('activity');
            $due = $this->exists('due_date') ? $this->input('due_date') : $activity?->due_date;
            $reminder = $this->exists('reminder_at') ? $this->input('reminder_at') : $activity?->reminder_at;
            if ($due && $reminder && strtotime((string) $reminder) > strtotime((string) $due)) {
                $validator->errors()->add('reminder_at', __('validation.before_or_equal', ['date' => 'due date']));
            }
        });
    }
}
