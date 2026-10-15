<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Campaign::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email_template_id' => ['required', 'exists:email_templates,id'],
            'segment_filters' => ['nullable', 'array'],
            'segment_filters.*' => ['nullable'],
            'segment_filters.search' => ['nullable', 'string', 'max:255'],
            'segment_filters.is_lead' => ['nullable', 'boolean'],
            'segment_filters.type' => ['nullable', Rule::in(Customer::TYPES)],
            'segment_filters.lead_status' => ['nullable', Rule::in(Customer::LEAD_STATUSES)],
            'segment_filters.status' => ['nullable', Rule::in(Customer::CUSTOMER_STATUSES)],
            'segment_filters.effective_status' => ['nullable', Rule::in([...Customer::LEAD_STATUSES, ...Customer::CUSTOMER_STATUSES])],
            'segment_filters.source' => ['nullable', Rule::in(Customer::SOURCES)],
            'segment_filters.assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'segment_filters.account_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'segment_filters.health_below' => ['nullable', 'integer', 'min:0', 'max:100'],
            'segment_filters.follow_up_due' => ['nullable', 'boolean'],
            'segment_filters.contact' => ['nullable', 'string', 'max:255'],
            'segment_filters.city' => ['nullable', 'string', 'max:255'],
            'segment_filters.address' => ['nullable', 'string', 'max:255'],
            'segment_filters.score_min' => ['nullable', 'integer', 'min:0', 'max:100'],
            'segment_filters.score_max' => ['nullable', 'integer', 'min:0', 'max:100'],
            'segment_filters.customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'segment_filters.tag' => ['nullable'],
        ];
    }
}
