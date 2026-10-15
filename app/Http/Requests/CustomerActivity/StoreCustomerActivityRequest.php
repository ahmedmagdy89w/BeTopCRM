<?php

namespace App\Http\Requests\CustomerActivity;

use App\Models\CustomerActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [CustomerActivity::class, $this->route('customer')]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(CustomerActivity::TYPES)],
            'channel' => ['nullable', 'string', Rule::in(CustomerActivity::CHANNELS)],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'reminder_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->sometimes('due_date', ['required'], fn ($input) => ($input->type ?? null) === 'follow_up');

        $validator->after(function ($validator) {
            $due = $this->input('due_date');
            $reminder = $this->input('reminder_at');
            if ($due && $reminder && strtotime((string) $reminder) > strtotime((string) $due)) {
                $validator->errors()->add('reminder_at', __('validation.before_or_equal', ['date' => 'due date']));
            }
        });
    }
}
