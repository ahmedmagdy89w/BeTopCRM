<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\CustomField\ValidatesCustomFields;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    use ValidatesCustomFields;

    public function authorize(): bool
    {
        return $this->user()->can('create', Customer::class);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Customer::TYPES)],
            'is_lead' => ['sometimes', 'boolean'],
            'lead_status' => ['nullable', 'string', Rule::in(Customer::LEAD_STATUSES)],
            'status' => ['sometimes', 'string', Rule::in(Customer::CUSTOMER_STATUSES)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'account_manager_id' => ['nullable', 'exists:users,id'],
            'source' => ['nullable', 'string', Rule::in(Customer::SOURCES)],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'expected_close_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            // Individual fields
            'first_name' => ['required_if:type,individual', 'nullable', 'string', 'max:100'],
            'last_name' => ['required_if:type,individual', 'nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'birthday' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            // Company fields
            'company_name' => ['required_if:type,company', 'nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            // Legacy contact snapshot (auto-derived from contacts[] when omitted)
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            // Contract / lifecycle
            'contract_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'health_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'next_follow_up_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            // Multiple contacts JSON
            'contacts' => ['nullable', 'array'],
            'contacts.*.first_name' => ['required_with:contacts', 'string', 'max:100'],
            'contacts.*.last_name' => ['required_with:contacts', 'string', 'max:100'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.title' => ['nullable', 'string', 'max:100'],
            'contacts.*.is_primary' => ['sometimes', 'boolean'],
            // Multiple addresses JSON
            'addresses' => ['nullable', 'array'],
            'addresses.*.label' => ['nullable', 'string', 'max:100'],
            'addresses.*.type' => ['nullable', 'string', 'max:30'],
            'addresses.*.line_1' => ['nullable', 'string', 'max:255'],
            'addresses.*.line_2' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:100'],
            'addresses.*.state' => ['nullable', 'string', 'max:100'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:20'],
            'addresses.*.country' => ['nullable', 'string', 'max:100'],
            'addresses.*.is_primary' => ['sometimes', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'custom_fields' => ['nullable', 'array'],
            ...$this->customFieldRules('customer'),
        ];
    }

    protected function defaultCustomFieldEntityType(): ?string
    {
        return 'customer';
    }
}
